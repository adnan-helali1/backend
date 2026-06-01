## مشروع B2B Mediation Platform (Backend API)

هذا المستودع يحتوي على **Backend API فقط** (Laravel 12) لمنصة تربط **الموردين** مع **المتاجر**.
لا يوجد تطبيق للموردين، والمورد هنا عبارة عن بيانات + عروض (أسعار/مخزون) يضيفها الأدمن.

الواجهة التي ستستهلك هذا الـ API:
- **Dashboard (Admin Web)** لإدارة النظام والإحصائيات.
- **تطبيق Store (Flutter)** لاستخدام المتجر (تصفح عروض الموردين، بناء كتالوج المتجر، عمليات شراء، عمليات بيع).

---

## 1 المفاهيم الأساسية (Business Concepts)

### 1.1 الأدوار (Actors)
- **Admin**
  - يدير الموردين، التصنيفات، المنتجات الأساسية، وعروض الموردين.
  - يرى كل طلبات الشراء والمبيعات والإحصائيات والدفتر المالي.
- **Store**
  - يمتلك حساب دخول (JWT).
  - يتصفح **عروض الموردين**، يختار منها كتالوج المتجر، ينشئ **طلبات شراء**، وينشئ **مبيعات** لعملائه.
- **Supplier**
  - **بدون تسجيل دخول**.
  - يتم تمثيله ببيانات وعروض منتجات (سعر شراء/مخزون/حالة) يحددها الأدمن.

### 1.2 المنتج الأساسي vs عرض المورد (Multi-supplier)
في النظام يوجد مستويين:

1) **Product (Master Product)**: المنتج ككيان عام (اسم + تصنيف + وصف…).

2) **SupplierProduct (Offer / عرض مورد لمنتج)**:
هو الربط بين `supplier_id` و `product_id` ويحتوي:
- `buy_price`: سعر الشراء من هذا المورد
- `stock_quantity`: مخزون هذا المورد للمنتج
- `status`: `available | unavailable | archived`

> نفس المنتج الأساسي يمكن أن يُعرض من أكثر من مورد، وكل مورد له سعر/مخزون مختلف.

### 1.3 كتالوج المتجر (Store Catalog)
الجدول `store_products` يمثل كتالوج المتجر، وهو اختيار المتجر لعروض معينة (`supplier_product_id`) مع:
- `sell_price` (اختياري): سعر البيع المقترح من المتجر
- `is_active`: هل المنتج فعال للبيع في المتجر

> المتجر يستطيع إضافة نفس المنتج الأساسي من موردين مختلفين لأن الكتالوج مربوط بـ `supplier_product_id`.

### 1.4 الشراء (Purchases)
الشراء هو **PurchaseOrder** من متجر إلى مورد.

- المتجر يرسل عناصر الطلب على شكل `supplier_product_id + quantity`.
- إذا العناصر تحتوي على أكثر من مورد:
  - النظام يقوم تلقائياً بإنشاء **عدة طلبات** (طلب لكل مورد) حتى تكون عملية الشراء واضحة في الداشبورد والمتجر.

حالات الشراء الحالية:
- `draft`
- `submitted`
- `cancelled`
- `received`

### 1.5 البيع (Sales)
البيع هو **SalesOrder** داخل المتجر لعملائه (ليس للمورد).

- يعتمد على `store_product_id` (عنصر من كتالوج المتجر).
- يتم حفظ snapshot للأسعار:
  - `unit_sell_price`
  - `unit_buy_price` (مأخوذ من `supplier_products.buy_price`)
- حساب الربح:
  - `profit = total - total_cost`

حالات البيع:
- `draft`
- `paid`
- `cancelled`

---

## 2) المصادقة (JWT Authentication)

يوجد حارسين JWT:
- `admin_api` للأدمن
- `store_api` للمتجر

### 2.1 كيف يرسل Flutter التوكن؟
أرسل الهيدر التالي مع أي endpoint محمي:

`Authorization: Bearer <token>`

### 2.2 Endpoints تسجيل الدخول
- **Admin**
  - `POST /api/admin/login`
  - `POST /api/admin/logout` (محمي)
- **Store**
  - `POST /api/store/register`
  - `POST /api/store/login`
  - `POST /api/store/logout` (محمي)

---

## 3) شكل الاستجابة القياسي + هندلة الأخطاء

### 3.1 نجاح (Success)
```json
{
  "data": { },
  "message": "Success",
  "errors": null
}
```

### 3.2 خطأ (Error)
أي خطأ على مسارات `api/*` يرجع شكل موحد:
```json
{
  "data": null,
  "message": "Validation error",
  "errors": { },
  "error_code": "VALIDATION_ERROR",
  "request_id": "uuid"
}
```

### 3.3 أكواد مهمة
- `UNAUTHENTICATED` (401)
- `TOKEN_EXPIRED` / `TOKEN_INVALID` (401)
- `FORBIDDEN` (403)
- `ROUTE_NOT_FOUND` (404)
- `VALIDATION_ERROR` (422)
- أخطاء بزنس مخصصة عبر `ApiException` مثل:
  - `ORDER_ITEMS_INVALID`
  - `ORDER_CANNOT_CANCEL`
  - `SUPPLIER_PRODUCT_NOT_AVAILABLE`
  - `SELL_PRICE_REQUIRED`

> سيتم إرسال `X-Request-Id` في response headers للتتبع + تسجيل أي 500 في اللوج مع request_id.

---

## 4) الصور (Images) عبر Media Library

الصور المستخدمة حالياً:
- **صورة التصنيف Category Image** عبر Spatie Media Library.

### 4.1 كيف يتم رفع صورة التصنيف؟
من الأدمن:
- `POST /api/admin/categories` (form-data)
- `PATCH /api/admin/categories/{id}` (form-data)

الحقول:
- `name` (text)
- `image` (file) اختياري (صورة)

### 4.2 كيف يحصل Flutter على رابط الصورة؟
`GET /api/admin/categories` أو `GET /api/admin/categories/{id}` يرجع:
- `image_url` داخل الـ Category.

> روابط الصور تعتمد على `APP_URL` + `/storage/...` لذلك يجب ضبط `APP_URL` على نفس الدومين/البورت.

---

## 5) قواعد مهمة لـ Flutter (Form-data + JSON string)

حسب طلب المشروع: عمليات الإضافة والتعديل نرسلها كـ **multipart/form-data**.

لكن يوجد حقول Arrays/Objects، لذلك نرسلها **كنص JSON** داخل form-data.

أمثلة:

### 5.1 إنشاء طلب شراء (Store)
`POST /api/store/orders` (form-data)
- `items` (text) يحتوي JSON Array:
```json
[{"supplier_product_id":1,"quantity":2},{"supplier_product_id":2,"quantity":1}]
```
- `notes` (text) اختياري

### 5.2 إنشاء فاتورة بيع (Store)
`POST /api/store/sales` (form-data)
- `items` (text) JSON Array:
```json
[{"store_product_id":1,"quantity":2,"unit_sell_price":12.5}]
```
- `customer` (text) JSON Object اختياري:
```json
{"name":"Customer","phone":"0999999999"}
```
- أو `customer_id` (text) لو عميل موجود

---

## 6) أهم Endpoints لمطور Flutter

### 6.1 Store - Profile
- `GET /api/store/profile`
- `PUT /api/store/profile` (form-data)

### 6.2 Store - Browse supplier offers
هذه النقطة مهمة: المتجر يتصفح **SupplierProduct** وليس Product.
- `GET /api/store/products`
  - filters: `supplier_id`, `category_id`, `search`, `in_stock_only`, `per_page`
- `GET /api/store/products/{supplierProductId}`

### 6.3 Store - Catalog
- `GET /api/store/catalog`
- `POST /api/store/catalog/{supplierProductId}` (form-data: `sell_price`, `is_active`)
- `PATCH /api/store/catalog/{supplierProductId}` (form-data)
- `DELETE /api/store/catalog/{supplierProductId}`

### 6.4 Store - Purchases (شراء من الموردين)
- `POST /api/store/orders` (form-data)
  - ينشئ **طلبات متعددة** عند تعدد الموردين داخل items.
- `GET /api/store/orders`
- `GET /api/store/orders/{id}`
- `PUT /api/store/orders/{id}/cancel`

### 6.5 Store - Ledger
- `GET /api/store/ledger`
  - يرجع entries + summary (credits/debits/balance)

### 6.6 Store - Sales (مبيعات المتجر)
- `GET /api/store/sales`
- `POST /api/store/sales` (form-data)
- `GET /api/store/sales/{id}`
- `POST /api/store/sales/{id}/pay` (form-data: `amount`, `method`, `occurred_at`)
- `PUT /api/store/sales/{id}/cancel`

---

## 7) Admin Dashboard Endpoints (مختصر)

### 7.1 CRUD (Suppliers / Categories / Products)
- Suppliers: `/api/admin/suppliers`
- Categories: `/api/admin/categories` (يدعم رفع الصورة)
- Products (Master): `/api/admin/products`

### 7.2 Supplier categories pivot
- `GET /api/admin/suppliers/{id}/categories`
- `PUT /api/admin/suppliers/{id}/categories` (form-data)

### 7.3 Supplier offers (multi-supplier)
- `GET /api/admin/supplier-products`
- `POST /api/admin/supplier-products` (form-data)
- `PATCH /api/admin/supplier-products/{id}/stock` (form-data)

### 7.4 Orders & Sales views
- Purchases: `GET /api/admin/orders` + `GET /api/admin/orders/{id}`
- Sales: `GET /api/admin/sales` + `GET /api/admin/sales/{id}`

### 7.5 Stats
- `GET /api/admin/stats/overview`
- `GET /api/admin/stats/users-summary`
- `GET /api/admin/stats/orders-trend`
- `GET /api/admin/stats/orders-by-status`
- `GET /api/admin/stats/sales-trend`
- `GET /api/admin/stats/top-stores`
- `GET /api/admin/stats/low-stock`

---

## 8) تشغيل المشروع محلياً (Local Setup)

### 8.1 إعداد البيئة
1) انسخ `.env.example` إلى `.env`
2) اضبط معلومات قاعدة البيانات MySQL
3) شغل:
```bash
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve --port=8001
```

> تأكد أن `APP_URL` يطابق عنوان السيرفر (مثال `http://127.0.0.1:8001`) حتى روابط الصور تعمل.

### 8.2 بيانات الدخول التجريبية (Seeded Accounts)
- Admin:
  - email: `admin@dashboard.test`
  - password: `Admin@12345`
- Store:
  - email: `store@client.test`
  - password: `Store@12345`

---

## 9) Postman Collection
يوجد ملف Postman جاهز ومقسّم ضمن folders ويستخدم form-data.
المسار:
- `postman/B2B-Mediation-API.postman_collection.json`

ملاحظة: Requests الـ login تحفظ التوكن تلقائياً في:
- `admin_token`
- `store_token`

تحديث: قمت بإضافة جميع الـ endpoints الناقصة الموجودة في `routes/api.php` إلى نسخة الـ Postman داخل الملف أعلاه، وتمت إضافة مجلد "Added - Missing Endpoints" الذي يحتوي على نقاط الإدارة والـ Store غير المدرجة سابقاً.

