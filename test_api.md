# 🧪 دليل اختبار API - Store Catalog

## ✅ البيانات المُجهزة

### 👥 حسابات الاختبار

#### 1. Admin (المسؤول)
```
Email: admin@dashboard.test
Password: Admin@12345
```

#### 2. Store (المتجر)
```
Email: store@client.test
Password: Store@12345
```

#### 3. Users (مستخدمون عاديون)
```
Email: user@example.com
Password: password
```

---

## 📊 البيانات في جدول `store_products`

| ID | Store ID | Product ID | Supplier Product ID | Sell Price | Active |
|----|----------|------------|---------------------|------------|--------|
| 1  | 1        | 1          | 1                   | 0.88       | ✅ Yes |
| 2  | 1        | 2          | 2                   | 1.49       | ✅ Yes |
| 3  | 1        | 3          | 3                   | 1.08       | ✅ Yes |
| 4  | 1        | 3          | 4                   | 1.01       | ✅ Yes |

**ملاحظة:** المنتج ID=3 موجود مرتين (من موردين مختلفين)

---

## 🔐 الخطوة 1: تسجيل الدخول والحصول على Token

### طريقة 1: باستخدام cURL

```bash
curl -X POST http://localhost:8000/api/store/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "store@client.test",
    "password": "Store@12345"
  }'
```

### طريقة 2: باستخدام PowerShell

```powershell
$loginData = @{
    email = "store@client.test"
    password = "Store@12345"
} | ConvertTo-Json

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/store/login" `
    -Method POST `
    -Body $loginData `
    -ContentType "application/json"

$token = $response.data.token
Write-Host "Token: $token"
```

### الاستجابة المتوقعة:

```json
{
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "store": {
      "id": 1,
      "name": "Demo Store",
      "email": "store@client.test"
    }
  }
}
```

---

## 📋 الخطوة 2: عرض الكتالوج (Catalog)

```bash
curl -X GET http://localhost:8000/api/store/catalog \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

**PowerShell:**
```powershell
$headers = @{
    "Authorization" = "Bearer $token"
}

Invoke-RestMethod -Uri "http://localhost:8000/api/store/catalog" `
    -Method GET `
    -Headers $headers
```

---

## ✏️ الخطوة 3: تحديث منتج في الكتالوج (الاختبار الرئيسي)

### ✅ تحديث المنتج ID=1

```bash
curl -X PATCH http://localhost:8000/api/store/catalog/1 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "sell_price": 1.25,
    "is_active": true
  }'
```

**PowerShell:**
```powershell
$updateData = @{
    sell_price = 1.25
    is_active = $true
} | ConvertTo-Json

$headers = @{
    "Authorization" = "Bearer $token"
    "Content-Type" = "application/json"
}

Invoke-RestMethod -Uri "http://localhost:8000/api/store/catalog/1" `
    -Method PATCH `
    -Headers $headers `
    -Body $updateData
```

### الاستجابة المتوقعة:

```json
{
  "data": {
    "id": 1,
    "store_id": 1,
    "product_id": 1,
    "supplier_product_id": 1,
    "sell_price": "1.25",
    "is_active": true,
    "supplier_product": {
      "id": 1,
      "buy_price": "0.65",
      "product": {
        "id": 1,
        "name": "Cola Can",
        "category": {
          "name": "Beverages"
        }
      }
    }
  },
  "message": "Updated",
  "errors": null
}
```

---

## 🧪 اختبارات إضافية

### 1. تعطيل منتج

```bash
curl -X PATCH http://localhost:8000/api/store/catalog/2 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "is_active": false
  }'
```

### 2. تحديث السعر فقط

```bash
curl -X PATCH http://localhost:8000/api/store/catalog/3 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "sell_price": 2.50
  }'
```

### 3. تحديث كامل

```bash
curl -X PATCH http://localhost:8000/api/store/catalog/4 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "sell_price": 3.00,
    "is_active": true
  }'
```

---

## ❌ حالات الخطأ المتوقعة

### 1. ID غير موجود (404)

```bash
curl -X PATCH http://localhost:8000/api/store/catalog/999 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{"sell_price": 1.00}'
```

### 2. بدون Token (401 Unauthorized)

```bash
curl -X PATCH http://localhost:8000/api/store/catalog/1 \
  -H "Content-Type: application/json" \
  -d '{"sell_price": 1.00}'
```

### 3. سعر سالب (422 Validation Error)

```bash
curl -X PATCH http://localhost:8000/api/store/catalog/1 \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{"sell_price": -10}'
```

---

## 📱 اختبار باستخدام Postman

### 1. إنشاء Environment جديد

```
Variable: base_url
Value: http://localhost:8000
```

```
Variable: store_token
Value: (ضع الـ token بعد Login)
```

### 2. إنشاء Collection

#### Request 1: Login
```
POST {{base_url}}/api/store/login
Body (raw JSON):
{
  "email": "store@client.test",
  "password": "Store@12345"
}

Tests (لحفظ Token تلقائياً):
pm.environment.set("store_token", pm.response.json().data.token);
```

#### Request 2: Get Catalog
```
GET {{base_url}}/api/store/catalog
Headers:
Authorization: Bearer {{store_token}}
```

#### Request 3: Update Catalog Item
```
PATCH {{base_url}}/api/store/catalog/1
Headers:
Authorization: Bearer {{store_token}}
Content-Type: application/json

Body (raw JSON):
{
  "sell_price": 1.25,
  "is_active": true
}
```

---

## 🔍 التحقق من التحديثات في قاعدة البيانات

بعد تنفيذ الاختبارات، يمكنك التحقق من البيانات:

```bash
php artisan tinker --execute="DB::table('store_products')->find(1)"
```

---

## 🎯 الملخص

✅ **تم تجهيز:**
- 1 Admin
- 1 Store  
- 10 Users
- 2 Suppliers
- 3 Categories
- 4 Products
- 5 Supplier Products
- 4 Store Products (في كتالوج المتجر)

✅ **جاهز للاختبار:**
- تسجيل الدخول
- عرض الكتالوج
- تحديث الأسعار والحالة
- جميع حالات الخطأ

---

## 🚀 بدء الخادم

إذا لم يكن الخادم يعمل:

```bash
php artisan serve
```

أو:

```bash
composer run dev
```

الآن يمكنك البدء بالاختبار! 🎉
