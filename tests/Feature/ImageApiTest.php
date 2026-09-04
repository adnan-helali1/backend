<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Store;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_image_is_returned_after_registration_and_in_admin_list_and_profile(): void
    {
        Storage::fake('public');

        $register = $this->post('/api/store/register', [
            'name' => 'Image Store',
            'owner_name' => 'Owner',
            'phone' => '123456789',
            'email' => 'image-store@example.com',
            'password' => 'password123',
            'address' => 'Damascus',
            'image' => $this->fakePng('store.png'),
        ]);

        $register
            ->assertCreated()
            ->assertJsonPath('data.store.name', 'Image Store');
        $this->assertNotNull($register->json('data.store.image_url'));
        $this->get(parse_url($register->json('data.store.image_url'), PHP_URL_PATH))->assertOk();

        $storeToken = $register->json('data.token');
        $profile = $this->withToken($storeToken)->getJson('/api/store/profile');
        $profile->assertOk();
        $this->assertSame($register->json('data.store.image_url'), $profile->json('data.image_url'));

        $updatedProfile = $this->withToken($storeToken)->put('/api/store/profile', [
            'image' => $this->fakePng('updated-store.png'),
        ]);
        $updatedProfile->assertOk();
        $this->assertNotSame($profile->json('data.image_url'), $updatedProfile->json('data.image_url'));

        $adminToken = $this->adminToken();
        $stores = $this->withToken($adminToken)->getJson('/api/admin/stores');
        $stores->assertOk();
        $this->assertSame($updatedProfile->json('data.image_url'), $stores->json('data.data.0.image_url'));
    }

    public function test_product_image_is_returned_for_supplier_product_store_products_and_offers(): void
    {
        Storage::fake('public');

        $supplier = Supplier::create([
            'name' => 'Supplier',
            'status' => 'active',
        ]);
        $category = Category::create(['name' => 'Category']);
        $supplier->categories()->attach($category->id);

        $adminToken = $this->adminToken();
        $productResponse = $this->withToken($adminToken)->post('/api/admin/products', [
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Product with image',
            'buy_price' => 10,
            'stock_quantity' => 20,
            'image' => $this->fakePng('product.png'),
        ]);

        $productResponse->assertCreated();
        $imageUrl = $productResponse->json('data.image_url');
        $this->assertNotNull($imageUrl);

        $supplierProductResponse = $this->withToken($adminToken)->postJson('/api/admin/supplier-products', [
            'supplier_id' => $supplier->id,
            'product_id' => $productResponse->json('data.id'),
            'buy_price' => 10,
            'stock_quantity' => 20,
        ]);
        $supplierProductResponse->assertCreated();
        $this->assertSame($imageUrl, $supplierProductResponse->json('data.image_url'));

        $offerResponse = $this->withToken($adminToken)->postJson('/api/admin/supplier-offers', [
            'supplier_product_id' => $supplierProductResponse->json('data.id'),
            'offer_price' => 8,
            'offer_stock' => 5,
        ]);
        $offerResponse->assertCreated();
        $this->assertSame($imageUrl, $offerResponse->json('data.image_url'));

        $store = Store::create([
            'name' => 'Store',
            'owner_name' => 'Owner',
            'phone' => '987654321',
            'email' => 'store@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $storeToken = auth('store_api')->login($store);

        $storeProducts = $this->withToken($storeToken)->getJson('/api/store/products');
        $storeProducts->assertOk();
        $this->assertSame($imageUrl, $storeProducts->json('data.data.0.image_url'));

        $offers = $this->withToken($storeToken)->getJson('/api/store/offers');
        $offers->assertOk();
        $this->assertSame($imageUrl, $offers->json('data.0.image_url'));

        $this->withToken($storeToken)->postJson('/api/store/catalog/'.$supplierProductResponse->json('data.id'), [
            'sell_price' => 12,
        ])->assertCreated();

        $catalog = $this->withToken($storeToken)->getJson('/api/store/catalog');
        $catalog->assertOk();
        $this->assertSame($imageUrl, $catalog->json('data.0.image_url'));
    }

    public function test_store_registration_and_product_creation_validate_required_images(): void
    {
        $this->postJson('/api/store/register', [
            'name' => 'Store',
            'owner_name' => 'Owner',
            'phone' => '123456789',
            'email' => 'missing-image@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('image');

        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);
        $category = Category::create(['name' => 'Category']);
        $supplier->categories()->attach($category->id);

        $this->withToken($this->adminToken())->postJson('/api/admin/products', [
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'name' => 'Missing image',
            'buy_price' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    private function adminToken(): string
    {
        $admin = Admin::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        return auth('admin_api')->login($admin);
    }

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        );
    }
}
