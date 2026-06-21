# ============================================
# 🧪 سكريبت اختبار Store Catalog API
# ============================================

$baseUrl = "http://localhost:8000"
$storeEmail = "store@client.test"
$storePassword = "Store@12345"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "🧪 بدء اختبار Store Catalog API" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# ============================================
# 1️⃣ تسجيل الدخول والحصول على Token
# ============================================
Write-Host "1️⃣ تسجيل دخول المتجر..." -ForegroundColor Yellow

try {
    $loginBody = @{
        email = $storeEmail
        password = $storePassword
    } | ConvertTo-Json

    $loginResponse = Invoke-RestMethod -Uri "$baseUrl/api/store/login" `
        -Method POST `
        -Body $loginBody `
        -ContentType "application/json"

    $token = $loginResponse.data.token
    $storeName = $loginResponse.data.store.name

    Write-Host "✅ تم تسجيل الدخول بنجاح!" -ForegroundColor Green
    Write-Host "   المتجر: $storeName" -ForegroundColor Gray
    Write-Host "   Token: $($token.Substring(0, 30))..." -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "❌ فشل تسجيل الدخول!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit
}

# ============================================
# 2️⃣ عرض الكتالوج
# ============================================
Write-Host "2️⃣ عرض كتالوج المنتجات..." -ForegroundColor Yellow

try {
    $headers = @{
        "Authorization" = "Bearer $token"
    }

    $catalogResponse = Invoke-RestMethod -Uri "$baseUrl/api/store/catalog" `
        -Method GET `
        -Headers $headers

    $products = $catalogResponse.data
    Write-Host "✅ تم جلب الكتالوج بنجاح!" -ForegroundColor Green
    Write-Host "   عدد المنتجات: $($products.Count)" -ForegroundColor Gray
    
    Write-Host "`n📋 قائمة المنتجات:" -ForegroundColor Cyan
    foreach ($product in $products) {
        $active = if ($product.is_active) { "✅" } else { "❌" }
        Write-Host "   ID: $($product.id) | السعر: $($product.sell_price) | حالة: $active | المنتج: $($product.supplier_product.product.name)" -ForegroundColor Gray
    }
    Write-Host ""
} catch {
    Write-Host "❌ فشل جلب الكتالوج!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}

# ============================================
# 3️⃣ تحديث المنتج الأول (ID=1)
# ============================================
Write-Host "3️⃣ تحديث المنتج ID=1..." -ForegroundColor Yellow

try {
    $updateBody = @{
        sell_price = 1.25
        is_active = $true
    } | ConvertTo-Json

    $headers = @{
        "Authorization" = "Bearer $token"
        "Content-Type" = "application/json"
    }

    $updateResponse = Invoke-RestMethod -Uri "$baseUrl/api/store/catalog/1" `
        -Method PATCH `
        -Headers $headers `
        -Body $updateBody

    Write-Host "✅ تم تحديث المنتج بنجاح!" -ForegroundColor Green
    Write-Host "   ID: $($updateResponse.data.id)" -ForegroundColor Gray
    Write-Host "   السعر الجديد: $($updateResponse.data.sell_price)" -ForegroundColor Gray
    Write-Host "   الحالة: $($updateResponse.data.is_active)" -ForegroundColor Gray
    Write-Host "   المنتج: $($updateResponse.data.supplier_product.product.name)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "❌ فشل تحديث المنتج!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}

# ============================================
# 4️⃣ تعطيل المنتج ID=2
# ============================================
Write-Host "4️⃣ تعطيل المنتج ID=2..." -ForegroundColor Yellow

try {
    $disableBody = @{
        is_active = $false
    } | ConvertTo-Json

    $disableResponse = Invoke-RestMethod -Uri "$baseUrl/api/store/catalog/2" `
        -Method PATCH `
        -Headers $headers `
        -Body $disableBody

    Write-Host "✅ تم تعطيل المنتج بنجاح!" -ForegroundColor Green
    Write-Host "   ID: $($disableResponse.data.id)" -ForegroundColor Gray
    Write-Host "   الحالة: $($disableResponse.data.is_active)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "❌ فشل تعطيل المنتج!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}

# ============================================
# 5️⃣ تحديث السعر فقط (ID=3)
# ============================================
Write-Host "5️⃣ تحديث سعر المنتج ID=3..." -ForegroundColor Yellow

try {
    $priceBody = @{
        sell_price = 2.50
    } | ConvertTo-Json

    $priceResponse = Invoke-RestMethod -Uri "$baseUrl/api/store/catalog/3" `
        -Method PATCH `
        -Headers $headers `
        -Body $priceBody

    Write-Host "✅ تم تحديث السعر بنجاح!" -ForegroundColor Green
    Write-Host "   ID: $($priceResponse.data.id)" -ForegroundColor Gray
    Write-Host "   السعر الجديد: $($priceResponse.data.sell_price)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "❌ فشل تحديث السعر!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}

# ============================================
# 6️⃣ اختبار خطأ: ID غير موجود
# ============================================
Write-Host "6️⃣ اختبار خطأ: ID غير موجود (999)..." -ForegroundColor Yellow

try {
    $errorBody = @{
        sell_price = 1.00
    } | ConvertTo-Json

    $errorResponse = Invoke-RestMethod -Uri "$baseUrl/api/store/catalog/999" `
        -Method PATCH `
        -Headers $headers `
        -Body $errorBody

    Write-Host "❓ لم يحدث خطأ (غير متوقع!)" -ForegroundColor Yellow
} catch {
    if ($_.Exception.Response.StatusCode -eq 404) {
        Write-Host "✅ الخطأ المتوقع: 404 Not Found" -ForegroundColor Green
    } else {
        Write-Host "⚠️ خطأ مختلف: $($_.Exception.Response.StatusCode)" -ForegroundColor Yellow
    }
}
Write-Host ""

# ============================================
# 7️⃣ اختبار خطأ: سعر سالب
# ============================================
Write-Host "7️⃣ اختبار خطأ: سعر سالب..." -ForegroundColor Yellow

try {
    $negativeBody = @{
        sell_price = -10
    } | ConvertTo-Json

    $negativeResponse = Invoke-RestMethod -Uri "$baseUrl/api/store/catalog/1" `
        -Method PATCH `
        -Headers $headers `
        -Body $negativeBody

    Write-Host "❓ لم يحدث خطأ (غير متوقع!)" -ForegroundColor Yellow
} catch {
    if ($_.Exception.Response.StatusCode -eq 422) {
        Write-Host "✅ الخطأ المتوقع: 422 Validation Error" -ForegroundColor Green
    } else {
        Write-Host "⚠️ خطأ مختلف: $($_.Exception.Response.StatusCode)" -ForegroundColor Yellow
    }
}
Write-Host ""

# ============================================
# 8️⃣ عرض الكتالوج النهائي
# ============================================
Write-Host "8️⃣ عرض الكتالوج بعد التحديثات..." -ForegroundColor Yellow

try {
    $finalCatalog = Invoke-RestMethod -Uri "$baseUrl/api/store/catalog" `
        -Method GET `
        -Headers @{ "Authorization" = "Bearer $token" }

    Write-Host "✅ الكتالوج النهائي:" -ForegroundColor Green
    foreach ($product in $finalCatalog.data) {
        $active = if ($product.is_active) { "✅" } else { "❌" }
        Write-Host "   ID: $($product.id) | السعر: $($product.sell_price) | حالة: $active" -ForegroundColor Gray
    }
    Write-Host ""
} catch {
    Write-Host "❌ فشل جلب الكتالوج!" -ForegroundColor Red
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "✅ انتهى الاختبار!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
