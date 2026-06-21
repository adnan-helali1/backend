# 🧪 دليل اختبار Order Status API

## 📋 نظرة عامة

**Endpoint جديد:** تغيير حالة الطلب (Admin فقط)

```
PUT /api/admin/orders/{order_id}/status
```

---

## 🔐 المتطلبات

### 1. تسجيل دخول Admin والحصول على Token

```http
POST http://localhost:8000/api/admin/login
Content-Type: application/json

{
  "email": "admin@dashboard.test",
  "password": "Admin@12345"
}
```

**احفظ الـ Token من الاستجابة!**

---

## 📊 الحالات المتاحة (Status)

| الحالة | الوصف |
|--------|-------|
| `draft` | مسودة (لم يتم إرساله بعد) |
| `submitted` | تم الإرسال (في انتظار المعالجة) |
| `received` | تم الاستلام (وصل للمتجر) |
| `cancelled` | ملغى |

---

## 🔄 الانتقالات المسموحة (Status Transitions)

```
draft → submitted ✅
draft → cancelled ✅

submitted → received ✅
submitted → cancelled ✅

received → (لا يمكن التغيير - حالة نهائية) ❌
cancelled → (لا يمكن التغيير - حالة نهائية) ❌
```

---

## 📦 البيانات الموجودة للاختبار

```json
{
  "id": 1,
  "store_id": 1,
  "supplier_id": 1,
  "status": "submitted",
  "total_buy": "1.30"
}
```

---

## 🧪 سيناريوهات الاختبار

### ✅ **سيناريو 1: تغيير من `submitted` إلى `received`**

**ماذا يحدث:**
- ✅ تحديث حالة الطلب
- ✅ إضافة المنتجات إلى مخزون المتجر
- ✅ تحديث ملاحظات دفتر الحسابات

```http
PUT http://localhost:8000/api/admin/orders/1/status
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "status": "received",
  "notes": "تم استلام الشحنة بالكامل"
}
```

**الاستجابة المتوقعة:**
```json
{
  "data": {
    "id": 1,
    "store_id": 1,
    "supplier_id": 1,
    "status": "received",
    "total_buy": "1.30",
    "notes": "[Admin] تم استلام الشحنة بالكامل",
    "store": {...},
    "supplier": {...},
    "items": [...]
  },
  "message": "Order status updated successfully",
  "errors": null
}
```

**التحقق:**
```bash
# تحقق من المخزون
php artisan tinker --execute="DB::table('store_inventories')->get()"
```

---

### ✅ **سيناريو 2: إلغاء طلب (من `submitted` إلى `cancelled`)**

⚠️ **ملاحظة:** إذا نفذت السيناريو 1، يجب إنشاء طلب جديد أولاً!

**ماذا يحدث:**
- ✅ تحديث حالة الطلب إلى `cancelled`
- ✅ إرجاع الكميات إلى مخزون المورد
- ✅ إلغاء الدين من دفتر الحسابات (credit entry)

```http
PUT http://localhost:8000/api/admin/orders/1/status
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "status": "cancelled",
  "notes": "تم الإلغاء بسبب عدم توفر الشحن"
}
```

**الاستجابة المتوقعة:**
```json
{
  "data": {
    "id": 1,
    "status": "cancelled",
    "notes": "[Admin] تم الإلغاء بسبب عدم توفر الشحن"
  },
  "message": "Order status updated successfully",
  "errors": null
}
```

---

### ❌ **سيناريو 3: محاولة تغيير غير مسموح (خطأ متوقع)**

**محاولة تغيير من `received` إلى أي حالة أخرى:**

```http
PUT http://localhost:8000/api/admin/orders/1/status
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "status": "submitted"
}
```

**الاستجابة المتوقعة:**
```json
{
  "data": null,
  "message": "Invalid status transition",
  "errors": {
    "status": [
      "Cannot change status from 'received' to 'submitted'. Allowed transitions: "
    ]
  }
}
```
**Status Code:** `422`

---

### ❌ **سيناريو 4: نفس الحالة (خطأ متوقع)**

```http
PUT http://localhost:8000/api/admin/orders/1/status
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "status": "submitted"
}
```

**الاستجابة (إذا كانت الحالة الحالية `submitted`):**
```json
{
  "data": null,
  "message": "Status is already set to submitted",
  "errors": {
    "status": ["Order is already in this status"]
  }
}
```
**Status Code:** `422`

---

### ❌ **سيناريو 5: حالة غير صحيحة (خطأ Validation)**

```http
PUT http://localhost:8000/api/admin/orders/1/status
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "status": "invalid_status"
}
```

**الاستجابة:**
```json
{
  "data": null,
  "message": "Validation error",
  "errors": {
    "status": [
      "The selected status is invalid."
    ]
  }
}
```
**Status Code:** `422`

---

### ❌ **سيناريو 6: بدون Token (خطأ مصادقة)**

```http
PUT http://localhost:8000/api/admin/orders/1/status
Content-Type: application/json

{
  "status": "received"
}
```

**الاستجابة:**
```json
{
  "message": "Unauthenticated."
}
```
**Status Code:** `401`

---

## 🎯 إنشاء طلب جديد للاختبار

إذا أردت إنشاء طلب جديد:

### الطريقة 1: من Store API

```http
POST http://localhost:8000/api/store/login
Content-Type: application/json

{
  "email": "store@client.test",
  "password": "Store@12345"
}
```

ثم:

```http
POST http://localhost:8000/api/store/orders
Authorization: Bearer {store_token}
Content-Type: application/json

{
  "items": [
    {
      "supplier_product_id": 1,
      "quantity": 10
    }
  ],
  "notes": "طلب اختبار"
}
```

### الطريقة 2: مباشرة من قاعدة البيانات

```bash
php artisan tinker
```

```php
DB::table('purchase_orders')->insert([
    'store_id' => 1,
    'supplier_id' => 1,
    'status' => 'submitted',
    'total_buy' => 10.00,
    'total_sell' => 13.50,
    'notes' => 'طلب اختبار',
    'created_at' => now(),
    'updated_at' => now()
]);

echo "Order ID: " . DB::getPdo()->lastInsertId();
```

---

## 📱 اختبار باستخدام Postman

### 1. استيراد Collection

أنشئ Collection جديدة باسم "Order Status API"

### 2. إضافة Requests

#### Request 1: Admin Login
```
POST {{base_url}}/api/admin/login
Body (JSON):
{
  "email": "admin@dashboard.test",
  "password": "Admin@12345"
}

Tests Script:
pm.collectionVariables.set("admin_token", pm.response.json().data.token);
```

#### Request 2: Get Orders
```
GET {{base_url}}/api/admin/orders
Headers:
Authorization: Bearer {{admin_token}}
```

#### Request 3: Update to Received
```
PUT {{base_url}}/api/admin/orders/1/status
Headers:
Authorization: Bearer {{admin_token}}
Content-Type: application/json

Body (JSON):
{
  "status": "received",
  "notes": "تم استلام الشحنة"
}
```

#### Request 4: Update to Cancelled
```
PUT {{base_url}}/api/admin/orders/2/status
Headers:
Authorization: Bearer {{admin_token}}
Content-Type: application/json

Body (JSON):
{
  "status": "cancelled",
  "notes": "تم الإلغاء"
}
```

---

## 🧪 سكريبت PowerShell للاختبار التلقائي

```powershell
# اختبار Order Status API

$baseUrl = "http://localhost:8000"

# 1. تسجيل دخول Admin
$loginResponse = Invoke-RestMethod -Uri "$baseUrl/api/admin/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body '{"email":"admin@dashboard.test","password":"Admin@12345"}'

$adminToken = $loginResponse.data.token
Write-Host "✅ Admin Token: $($adminToken.Substring(0, 20))..." -ForegroundColor Green

# 2. عرض الطلبات
$orders = Invoke-RestMethod -Uri "$baseUrl/api/admin/orders" `
    -Method GET `
    -Headers @{"Authorization" = "Bearer $adminToken"}

Write-Host "`n📦 الطلبات الموجودة:" -ForegroundColor Cyan
$orders.data.data | ForEach-Object {
    Write-Host "   ID: $($_.id) | Status: $($_.status) | Total: $($_.total_buy)" -ForegroundColor Gray
}

# 3. تحديث حالة الطلب الأول
$orderId = $orders.data.data[0].id
Write-Host "`n🔄 تحديث حالة الطلب ID=$orderId إلى 'received'..." -ForegroundColor Yellow

try {
    $updateResponse = Invoke-RestMethod -Uri "$baseUrl/api/admin/orders/$orderId/status" `
        -Method PUT `
        -Headers @{
            "Authorization" = "Bearer $adminToken"
            "Content-Type" = "application/json"
        } `
        -Body '{"status":"received","notes":"تم استلام الشحنة بنجاح"}'

    Write-Host "✅ تم التحديث بنجاح!" -ForegroundColor Green
    Write-Host "   الحالة الجديدة: $($updateResponse.data.status)" -ForegroundColor Gray
} catch {
    Write-Host "❌ فشل التحديث: $($_.Exception.Message)" -ForegroundColor Red
}
```

حفظ الملف: `test_order_status.ps1`

تشغيله:
```powershell
.\test_order_status.ps1
```

---

## 📊 التحقق من التغييرات

### التحقق من المخزون بعد `received`:

```bash
php artisan tinker --execute="DB::table('store_inventories')->get()"
```

### التحقق من دفتر الحسابات:

```bash
php artisan tinker --execute="DB::table('store_ledger_entries')->where('source_type', 'order')->get()"
```

### التحقق من مخزون المورد بعد `cancelled`:

```bash
php artisan tinker --execute="DB::table('supplier_products')->get()"
```

---

## 📋 ملخص الحالات

| الحالة الحالية | الحالات المسموحة | ماذا يحدث |
|----------------|------------------|-----------|
| **draft** | `submitted`, `cancelled` | لا شيء (مجرد تحديث) |
| **submitted** | `received`, `cancelled` | received: إضافة للمخزون<br>cancelled: إرجاع للمورد |
| **received** | ❌ لا يوجد | حالة نهائية |
| **cancelled** | ❌ لا يوجد | حالة نهائية |

---

## ✅ جاهز للاختبار!

ابدأ بـ:
1. تسجيل دخول Admin
2. عرض الطلبات الموجودة
3. تحديث حالة طلب
4. التحقق من النتائج

🚀 **Good Luck!**
