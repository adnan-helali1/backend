# 🛒 التعديلات الجديدة على API المخزون والمبيعات

> للمطور Flutter — جميع الـ endpoints تتطلب **توكن المتجر** (`auth:store_api`).

---

## 1. إنشاء فاتورة بيع — `POST /api/store/sales`

**📍 تنقص كمية المخزون تلقائياً**

### Body

```json
{
  "items": [
    {
      "store_product_id": 1,
      "quantity": 2,
      "unit_sell_price": 1.50
    }
  ],
  "customer": {
    "name": "أحمد",
    "phone": "0912345678"
  },
  "notes": "شكراً"
}
```

| الحقل | النوع | إجباري | الشرح |
|---|---|---|---|
| `items` | array | ✅ | مصفوفة المنتجات |
| `items[].store_product_id` | int | ✅ | ID المنتج من الكتالوج |
| `items[].quantity` | int | ✅ | الكمية (1+) |
| `items[].unit_sell_price` | float | لا | سعر البيع (يستخدم سعر الكتالوج إن لم يُرسل) |
| `customer.name` | string | مع customer | |
| `customer.phone` | string | لا | |
| `customer_id` | int | لا | ID زبون موجود مسبقاً |
| `notes` | string | لا | |

### Response `201`

```json
{
  "data": {
    "id": 1,
    "status": "draft",
    "total": 3.00,
    "items": [...]
  },
  "message": "Created"
}
```

### 🆕 التغيير

عند إنشاء فاتورة بيع، ينقص `StoreInventory.quantity` لكل منتج تلقائياً. لم يعد المخزون ثابتاً — كل عملية بيع تنقص الكمية الفعلية.

---

## 2. إضافة كمية يدوية للمخزون — `POST /api/store/inventory/manual-add`

**📍 تزيد كمية المخزون + تسجل عملية شراء خارجي**

### Body

```json
{
  "store_product_id": 1,
  "quantity": 50,
  "unit_price": 0.65,
  "seller_name": "مورد خارجي",
  "occurred_at": "2026-06-21 10:00:00",
  "notes": "شراء نقدي"
}
```

| الحقل | النوع | إجباري | الشرح |
|---|---|---|---|
| `store_product_id` | int | ✅ | ID المنتج من الكتالوج |
| `quantity` | int | ✅ | الكمية المضافة (1+) |
| `unit_price` | float | ✅ | سعر الشراء للوحدة |
| `seller_name` | string | ✅ | اسم البائع / المورد |
| `occurred_at` | datetime | لا | تاريخ الشراء (الآن افتراضياً) |
| `notes` | string | لا | ملاحظات (500 حرف كحد أقصى) |

### Response `201`

```json
{
  "data": {
    "id": 1,
    "store_id": 1,
    "store_product_id": 1,
    "quantity": 50,
    "unit_price": "0.65",
    "seller_name": "مورد خارجي",
    "occurred_at": "2026-06-21T10:00:00Z",
    "notes": "شراء نقدي",
    "store_product": {
      "id": 1,
      "sell_price": "0.88",
      "supplier_product": {
        "buy_price": "0.65",
        "product": { "id": 1, "name": "Cola Can" }
      }
    }
  },
  "message": "Stock added successfully"
}
```

### Logic

- ينشئ سجل في جدول `external_purchases`
- يزيد `StoreInventory.quantity` بنفس الكمية (increment)

---

## 3. عرض المشتريات الخارجية — `GET /api/store/external-purchases`

**📍 عرض سجل عمليات شراء المخزون من خارج التطبيق**

### Parameters (Query)

| param | type | default | description |
|---|---|---|---|
| `per_page` | int | 15 | عدد النتائج في الصفحة |
| `page` | int | 1 | رقم الصفحة |

### Response `200`

```json
{
  "data": [
    {
      "id": 1,
      "store_id": 1,
      "store_product_id": 1,
      "quantity": 50,
      "unit_price": "0.65",
      "seller_name": "مورد خارجي",
      "occurred_at": "2026-06-21T10:00:00Z",
      "notes": "شراء نقدي",
      "store_product": {
        "id": 1,
        "sell_price": "0.88",
        "is_active": true,
        "supplier_product": {
          "buy_price": "0.65",
          "product": { "id": 1, "name": "Cola Can", "category": { ... } }
        }
      }
    }
  ],
  "message": "Success",
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

---

## ملخص تدفق المخزون

```
POST /api/store/sales
  → ينقص StoreInventory.quantity

POST /api/store/inventory/manual-add
  → يزيد StoreInventory.quantity
  → ينشئ ExternalPurchase (سجل الشراء)

GET  /api/store/inventory
  → يعرض المخزون الحالي (StoreInventory)

GET  /api/store/external-purchases
  → يعرض سجل المشتريات الخارجية (ExternalPurchase)
```

## مثال كامل من Flutter

### 1. إضافة مخزون يدوي

```dart
final response = await http.post(
  Uri.parse('$baseUrl/api/store/inventory/manual-add'),
  headers: {
    'Authorization': 'Bearer $token',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: jsonEncode({
    'store_product_id': 1,
    'quantity': 50,
    'unit_price': 0.65,
    'seller_name': 'مورد خارجي',
  }),
);
```

### 2. عرض المشتريات الخارجية

```dart
final response = await http.get(
  Uri.parse('$baseUrl/api/store/external-purchases?per_page=20'),
  headers: {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
  },
);
```

### 3. عرض المخزون الحالي

```dart
final response = await http.get(
  Uri.parse('$baseUrl/api/store/inventory'),
  headers: {
    'Authorization': 'Bearer $token',
    'Accept': 'application/json',
  },
);
```
