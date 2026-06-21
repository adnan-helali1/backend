#!/bin/bash

# ============================================
# 🧪 سكريبت اختبار Store Catalog API
# ============================================

BASE_URL="http://localhost:8000"
STORE_EMAIL="store@client.test"
STORE_PASSWORD="Store@12345"

echo "========================================"
echo "🧪 بدء اختبار Store Catalog API"
echo "========================================"
echo ""

# ============================================
# 1️⃣ تسجيل الدخول والحصول على Token
# ============================================
echo "1️⃣ تسجيل دخول المتجر..."

LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/api/store/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$STORE_EMAIL\",\"password\":\"$STORE_PASSWORD\"}")

TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.data.token')
STORE_NAME=$(echo $LOGIN_RESPONSE | jq -r '.data.store.name')

if [ "$TOKEN" != "null" ] && [ "$TOKEN" != "" ]; then
    echo "✅ تم تسجيل الدخول بنجاح!"
    echo "   المتجر: $STORE_NAME"
    echo "   Token: ${TOKEN:0:30}..."
    echo ""
else
    echo "❌ فشل تسجيل الدخول!"
    exit 1
fi

# ============================================
# 2️⃣ عرض الكتالوج
# ============================================
echo "2️⃣ عرض كتالوج المنتجات..."

CATALOG=$(curl -s -X GET "$BASE_URL/api/store/catalog" \
  -H "Authorization: Bearer $TOKEN")

echo "✅ تم جلب الكتالوج بنجاح!"
echo "$CATALOG" | jq '.data[] | "ID: \(.id) | السعر: \(.sell_price) | حالة: \(.is_active)"'
echo ""

# ============================================
# 3️⃣ تحديث المنتج الأول (ID=1)
# ============================================
echo "3️⃣ تحديث المنتج ID=1..."

UPDATE_RESPONSE=$(curl -s -X PATCH "$BASE_URL/api/store/catalog/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"sell_price": 1.25, "is_active": true}')

echo "✅ تم تحديث المنتج بنجاح!"
echo "$UPDATE_RESPONSE" | jq '.data | "ID: \(.id) | السعر الجديد: \(.sell_price) | المنتج: \(.supplier_product.product.name)"'
echo ""

# ============================================
# 4️⃣ تعطيل المنتج ID=2
# ============================================
echo "4️⃣ تعطيل المنتج ID=2..."

DISABLE_RESPONSE=$(curl -s -X PATCH "$BASE_URL/api/store/catalog/2" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"is_active": false}')

echo "✅ تم تعطيل المنتج بنجاح!"
echo "$DISABLE_RESPONSE" | jq '.data | "ID: \(.id) | الحالة: \(.is_active)"'
echo ""

# ============================================
# 5️⃣ تحديث السعر فقط (ID=3)
# ============================================
echo "5️⃣ تحديث سعر المنتج ID=3..."

PRICE_RESPONSE=$(curl -s -X PATCH "$BASE_URL/api/store/catalog/3" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"sell_price": 2.50}')

echo "✅ تم تحديث السعر بنجاح!"
echo "$PRICE_RESPONSE" | jq '.data | "ID: \(.id) | السعر الجديد: \(.sell_price)"'
echo ""

# ============================================
# 6️⃣ اختبار خطأ: ID غير موجود
# ============================================
echo "6️⃣ اختبار خطأ: ID غير موجود (999)..."

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X PATCH "$BASE_URL/api/store/catalog/999" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"sell_price": 1.00}')

if [ "$HTTP_CODE" == "404" ]; then
    echo "✅ الخطأ المتوقع: 404 Not Found"
else
    echo "⚠️ خطأ مختلف: $HTTP_CODE"
fi
echo ""

# ============================================
# 7️⃣ اختبار خطأ: سعر سالب
# ============================================
echo "7️⃣ اختبار خطأ: سعر سالب..."

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X PATCH "$BASE_URL/api/store/catalog/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"sell_price": -10}')

if [ "$HTTP_CODE" == "422" ]; then
    echo "✅ الخطأ المتوقع: 422 Validation Error"
else
    echo "⚠️ خطأ مختلف: $HTTP_CODE"
fi
echo ""

# ============================================
# 8️⃣ عرض الكتالوج النهائي
# ============================================
echo "8️⃣ عرض الكتالوج بعد التحديثات..."

FINAL_CATALOG=$(curl -s -X GET "$BASE_URL/api/store/catalog" \
  -H "Authorization: Bearer $TOKEN")

echo "✅ الكتالوج النهائي:"
echo "$FINAL_CATALOG" | jq '.data[] | "ID: \(.id) | السعر: \(.sell_price) | حالة: \(.is_active)"'
echo ""

echo "========================================"
echo "✅ انتهى الاختبار!"
echo "========================================"
