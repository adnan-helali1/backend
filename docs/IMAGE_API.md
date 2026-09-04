# Store and product images

All uploaded images use the `image` multipart field. Supported formats are JPG, JPEG, PNG, and WebP, with a maximum size of 5 MB. Responses expose an absolute URL in `image_url`.

The `public` filesystem disk serves `/storage/{path}` directly. Set `APP_URL` to the deployed backend origin so generated URLs are correct.

## Stores

- `POST /api/store/register`: `image` is required and the request must use `multipart/form-data`.
- `PUT /api/store/profile`: `image` is optional; when present it replaces the previous store image.
- Register, login, store profile, admin store list, and admin store details responses include `image_url`.

## Products

- `POST /api/admin/products`: `image` is required and the request must use `multipart/form-data`.
- `PUT|PATCH /api/admin/products/{product}`: `image` is optional and replaces the previous product image.
- `POST|PUT|PATCH /api/admin/supplier-products`: `image` is optional and updates the linked master product image.
- Supplier-product, store-product, offer, and catalog responses inherit the master product `image_url`.
- Catalog responses use `image_url` for the product and `category_image_url` for the category.

## Supplier offers

Admin CRUD routes are available under `/api/admin/supplier-offers`. Create and update accept an optional `image`; when supplied, it updates the linked master product image.

For multipart updates, clients that cannot send files with PUT/PATCH can send a POST multipart request with `_method=PUT` or `_method=PATCH`.
