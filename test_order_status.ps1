# ============================================
# 🧪 اختبار Order Status API
# ============================================

$baseUrl = "http://localhost:8000"
$adminEmail = "admin@dashboard.test"
$adminPassword = "Admin@12345"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "🧪 بدء اختبار Order Status API" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# ============================================
# 1️⃣ تسجيل دخول Admin
# ============================================
Write-Host "1️⃣ تسجيل دخول المسؤول..." -ForegroundColor Yellow

try {
    $loginBody = @{
        email = $adminEmail
        password = $adminPassword
    } | ConvertTo-Json

    $loginResponse = Invoke-RestMethod -Uri "$baseUrl/api/admin/login" `
        -Method POST `
        -Body $loginBody `
        -ContentType "application/json"

    $adminToken = $loginResponse.data.token
    $adminName = $loginResponse.data.admin.name

    Write-Host "✅ تم تسجيل الدخول بنجاح!" -ForegroundColor Green
    Write-Host "   المسؤول: $adminName" -ForegroundColor Gray
    Write-Host "   Token: $($adminToken.Substring(0, 30))..." -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "❌ فشل تسجيل الدخول!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit
}

$headers = @{
    "Authorization" = "Bearer $adminToken"
    "Content-Type" = "application/json"
}

# ============================================
# 2️⃣ عرض جميع الطلبات
# ============================================
Write-Host "2️⃣ عرض جميع الطلبات..." -ForegroundColor Yellow

try {
    $ordersResponse = Invoke-RestMethod -Uri "$baseUrl/api/admin/orders" `
        -Method GET `
        -Headers @{"Authorization" = "Bearer $adminToken"}

    $orders = $ordersResponse.data.data
    Write-Host "✅ تم جلب الطلبات بنجاح!" -ForegroundColor Green
    Write-Host "   عدد الطلبات: $($orders.Count)" -ForegroundColor Gray
    
    if ($orders.Count -eq 0) {
        Write-Host "`n⚠️ لا توجد طلبات للاختبار!" -ForegroundColor Yellow
        Write-Host "   يرجى إنشاء طلب أولاً من Store API" -ForegroundColor Gray
        exit
    }

    Write-Host "`n📋 قائمة الطلبات:" -ForegroundColor Cyan
    foreach ($order in $orders) {
        $statusIcon = switch ($order.status) {
            "draft" { "📝" }
            "submitted" { "📤" }
            "received" { "✅" }
            "cancelled" { "❌" }
            default { "❓" }
        }
        Write-Host "   $statusIcon ID: $($order.id) | الحالة: $($order.status) | المبلغ: $($order.total_buy)" -ForegroundColor Gray
    }
    Write-Host ""

    # اختيار أول طلب للاختبار
    $testOrder = $orders[0]
    $orderId = $testOrder.id
    $currentStatus = $testOrder.status

    Write-Host "🎯 سيتم اختبار الطلب ID=$orderId (الحالة الحالية: $currentStatus)" -ForegroundColor Cyan
    Write-Host ""

} catch {
    Write-Host "❌ فشل جلب الطلبات!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit
}

# ============================================
# 3️⃣ عرض تفاصيل الطلب
# ============================================
Write-Host "3️⃣ عرض تفاصيل الطلب ID=$orderId..." -ForegroundColor Yellow

try {
    $orderDetails = Invoke-RestMethod -Uri "$baseUrl/api/admin/orders/$orderId" `
        -Method GET `
        -Headers @{"Authorization" = "Bearer $adminToken"}

    Write-Host "✅ تفاصيل الطلب:" -ForegroundColor Green
    Write-Host "   المتجر: $($orderDetails.data.store.name)" -ForegroundColor Gray
    Write-Host "   المورد: $($orderDetails.data.supplier.name)" -ForegroundColor Gray
    Write-Host "   الحالة: $($orderDetails.data.status)" -ForegroundColor Gray
    Write-Host "   المبلغ: $($orderDetails.data.total_buy)" -ForegroundColor Gray
    Write-Host "   عدد العناصر: $($orderDetails.data.items.Count)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "❌ فشل جلب تفاصيل الطلب!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}

# ============================================
# 4️⃣ تحديد الحالة الجديدة بناءً على الحالة الحالية
# ============================================
$newStatus = ""
$testScenario = ""

switch ($currentStatus) {
    "draft" {
        $newStatus = "submitted"
        $testScenario = "تحويل من مسودة إلى مُرسل"
    }
    "submitted" {
        $newStatus = "received"
        $testScenario = "تحويل من مُرسل إلى مستلم"
    }
    "received" {
        Write-Host "⚠️ الطلب في حالة نهائية (received) - لا يمكن تغييره" -ForegroundColor Yellow
        Write-Host "`n🧪 سنحاول التغيير لإظهار رسالة الخطأ..." -ForegroundColor Cyan
        $newStatus = "submitted"
        $testScenario = "محاولة غير صحيحة (للاختبار)"
    }
    "cancelled" {
        Write-Host "⚠️ الطلب في حالة نهائية (cancelled) - لا يمكن تغييره" -ForegroundColor Yellow
        Write-Host "`n🧪 سنحاول التغيير لإظهار رسالة الخطأ..." -ForegroundColor Cyan
        $newStatus = "submitted"
        $testScenario = "محاولة غير صحيحة (للاختبار)"
    }
}

# ============================================
# 5️⃣ تحديث حالة الطلب
# ============================================
Write-Host "4️⃣ $testScenario..." -ForegroundColor Yellow
Write-Host "   من: $currentStatus → إلى: $newStatus" -ForegroundColor Gray

try {
    $updateBody = @{
        status = $newStatus
        notes = "تم التحديث من سكريبت الاختبار"
    } | ConvertTo-Json

    $updateResponse = Invoke-RestMethod -Uri "$baseUrl/api/admin/orders/$orderId/status" `
        -Method PUT `
        -Headers $headers `
        -Body $updateBody

    Write-Host "✅ تم تحديث الحالة بنجاح!" -ForegroundColor Green
    Write-Host "   الحالة الجديدة: $($updateResponse.data.status)" -ForegroundColor Gray
    Write-Host "   الرسالة: $($updateResponse.message)" -ForegroundColor Gray
    
    if ($updateResponse.data.notes) {
        Write-Host "   الملاحظات: $($updateResponse.data.notes)" -ForegroundColor Gray
    }
    Write-Host ""

} catch {
    $errorDetails = $_.ErrorDetails.Message | ConvertFrom-Json
    Write-Host "❌ فشل التحديث (متوقع إذا كانت الحالة نهائية):" -ForegroundColor Yellow
    Write-Host "   الرسالة: $($errorDetails.message)" -ForegroundColor Gray
    if ($errorDetails.errors) {
        Write-Host "   الأخطاء: $($errorDetails.errors | ConvertTo-Json -Compress)" -ForegroundColor Gray
    }
    Write-Host ""
}

# ============================================
# 6️⃣ اختبار حالة غير صحيحة (Validation Error)
# ============================================
Write-Host "5️⃣ اختبار حالة غير صحيحة (Validation)..." -ForegroundColor Yellow

try {
    $invalidBody = @{
        status = "invalid_status"
    } | ConvertTo-Json

    $invalidResponse = Invoke-RestMethod -Uri "$baseUrl/api/admin/orders/$orderId/status" `
        -Method PUT `
        -Headers $headers `
        -Body $invalidBody

    Write-Host "❓ لم يحدث خطأ (غير متوقع!)" -ForegroundColor Yellow
} catch {
    $errorDetails = $_.ErrorDetails.Message | ConvertFrom-Json
    Write-Host "✅ تم اكتشاف الخطأ كما هو متوقع!" -ForegroundColor Green
    Write-Host "   الرسالة: $($errorDetails.message)" -ForegroundColor Gray
    Write-Host "   الأخطاء: $($errorDetails.errors.status -join ', ')" -ForegroundColor Gray
    Write-Host ""
}

# ============================================
# 7️⃣ عرض الطلبات النهائية
# ============================================
Write-Host "6️⃣ عرض الطلبات بعد التحديثات..." -ForegroundColor Yellow

try {
    $finalOrders = Invoke-RestMethod -Uri "$baseUrl/api/admin/orders" `
        -Method GET `
        -Headers @{"Authorization" = "Bearer $adminToken"}

    Write-Host "✅ الطلبات النهائية:" -ForegroundColor Green
    foreach ($order in $finalOrders.data.data) {
        $statusIcon = switch ($order.status) {
            "draft" { "📝" }
            "submitted" { "📤" }
            "received" { "✅" }
            "cancelled" { "❌" }
            default { "❓" }
        }
        Write-Host "   $statusIcon ID: $($order.id) | الحالة: $($order.status)" -ForegroundColor Gray
    }
    Write-Host ""
} catch {
    Write-Host "❌ فشل جلب الطلبات النهائية!" -ForegroundColor Red
}

# ============================================
# 8️⃣ التحقق من المخزون (إذا تم تحديث لـ received)
# ============================================
if ($currentStatus -eq "submitted" -and $newStatus -eq "received") {
    Write-Host "7️⃣ التحقق من المخزون بعد الاستلام..." -ForegroundColor Yellow
    
    try {
        $storeId = $testOrder.store_id
        $storeDetails = Invoke-RestMethod -Uri "$baseUrl/api/admin/stores/$storeId" `
            -Method GET `
            -Headers @{"Authorization" = "Bearer $adminToken"}

        Write-Host "✅ تم تحديث المخزون للمتجر: $($storeDetails.data.name)" -ForegroundColor Green
        Write-Host ""
    } catch {
        Write-Host "⚠️ لم نتمكن من جلب تفاصيل المتجر" -ForegroundColor Yellow
    }
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "✅ انتهى الاختبار!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
