# ⚡ دليل الاختبار السريع - Order Status API

## 🚀 البدء السريع (3 خطوات)

### 1️⃣ **تشغيل الخادم**

```bash
php artisan serve
```

---

### 2️⃣ **اختيار طريقة الاختبار:**

#### ✅ **الطريقة 1: PowerShell (الأسرع - موصى به)**

```powershell
.\test_order_status.ps1
```

#### ✅ **الطريقة 2: Postman**

1. استيراد `postman_order_status.json`
2. تشغيل "1. Admin Login"
3. تشغيل "4. Update Status to RECEIVED"

#### ✅ **الطريقة 3: يدوياً**

```bash
# 1. تسجيل دخول
curl -X POST http://localhost:8000/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@dashboard.test","password":"Admin@12345"}'

# احفظ الـ token

# 2. تحديث الحالة
curl -X PUT http://localhost:8000/api/admin/orders/1/status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"received","notes":"تم الاستلام"}'
```

---

### 3️⃣ **التحقق من النتائج**

```bash
# عرض الطلبات
php artisan tinker --execute="DB::table('purchase_orders')->get()"

# عرض المخزون (بعد received)
php artisan tinker --execute="DB::table('store_inventories')->get()"

# عرض دفتر الحسابات
php artisan tinker --execute="DB::table('store_ledger_entries')->where('source_type','order')->get()"
```

---

## 📋 معلومات الحسابات

| النوع | Email | Password |
|------|-------|----------|
| **Admin** | `admin@dashboard.test` | `Admin@12345` |
| **Store** | `store@client.test` | `Store@12345` |

---

## 🎯 API Endpoint

```
PUT /api/admin/orders/{order_id}/status
```

**Body:**
```json
{
  "status": "received",
  "notes": "ملاحظات اختيارية"
}
```

**الحالات المتاحة:**
- `draft` - مسودة
- `submitted` - مُرسل
- `received` - مُستلم
- `cancelled` - ملغى

---

## 📊 الانتقالات المسموحة

```
draft → submitted ✅
draft → cancelled ✅

submitted → received ✅
submitted → cancelled ✅

received → (نهائي) ❌
cancelled → (نهائي) ❌
```

---

## 🔧 إنشاء طلب جديد (إذا لزم الأمر)

### من Store API:

```bash
# 1. تسجيل دخول متجر
curl -X POST http://localhost:8000/api/store/login \
  -H "Content-Type: application/json" \
  -d '{"email":"store@client.test","password":"Store@12345"}'

# 2. إنشاء طلب
curl -X POST http://localhost:8000/api/store/orders \
  -H "Authorization: Bearer STORE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"items":[{"supplier_product_id":1,"quantity":10}]}'
```

---

## 📁 الملفات المُنشأة

| الملف | الوصف |
|------|-------|
| `TEST_ORDER_STATUS_API.md` | دليل شامل مفصل |
| `postman_order_status.json` | مجموعة Postman جاهزة |
| `test_order_status.ps1` | سكريبت PowerShell تلقائي |
| `QUICK_TEST_GUIDE.md` | هذا الملف (دليل سريع) |

---

## ✅ نتائج متوقعة

### عند `received`:
- ✅ تحديث حالة الطلب
- ✅ إضافة المنتجات للمخزون (`store_inventories`)
- ✅ تحديث دفتر الحسابات

### عند `cancelled`:
- ✅ تحديث حالة الطلب
- ✅ إرجاع الكمية لمخزون المورد
- ✅ إضافة credit entry لدفتر الحسابات

---

## 🎉 جاهز!

اختر طريقة الاختبار وابدأ! 🚀
