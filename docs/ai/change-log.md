
---

## 2026-10-01 - Phase I Complete: Purchase Receipt (Phiếu nhập kho)

### Decision

Xây chứng từ nhập kho từ nhà cung cấp, nối nghiệp vụ chứng từ với Inventory Core hiện có:

- Database: `purchase_receipts` (receipt_code unique, supplier_id, warehouse_id, status `draft|posted`, receipt_date, note, created_by, posted_at) và `purchase_receipt_items` (purchase_receipt_id, product_id, quantity, unit_price nullable, expiry_date nullable). FK NOT NULL dùng `restrictOnDelete`.
- Model/Repository/Service: `PurchaseReceipt`, `PurchaseReceiptItem`, `PurchaseReceiptRepository`, `PurchaseReceiptItemRepository`, `PurchaseReceiptService` (createDraft, updateDraft, post).
- HTTP: `PurchaseReceiptController`, `StorePurchaseReceiptRequest`, routes `admin.purchase-receipts.*`, 3 trang Vue (`Admin/PurchaseReceipts/Index|Create|Show.vue`).
- Permission: module `purchase-receipt` với `view`/`create`/`post`, gán cho admin + manager qua `PurchaseReceiptPermissionSeeder`.
- Tests: `tests/Feature/PurchaseReceipt/PurchaseReceiptTest.php` (13 test: domain + HTTP).

Flow: Supplier -> PurchaseReceipt(draft) -> items -> POST -> `InventoryService::receiveStock()` từng item (trong 1 transaction, row lock trên receipt) -> Stock + StockLot + StockMovement(in, reference_type `purchase_receipt`) -> status `posted`.

### Reason

Phase I trong roadmap sau Inventory A→H: chứng từ nghiệp vụ nhập kho thay cho nhập kho chỉ ghi `note`.

### Impact

`InventoryService` KHÔNG đổi (đã hỗ trợ `meta.reference_type/reference_id/user_id/note`). `StockLot.received_at` = thời điểm POST (không phải `receipt_date`, để không phá thứ tự FIFO). POST lần hai bị chặn; 1 item lỗi thì rollback cả phiếu (kể cả status). Phiếu POSTED không sửa/xoá được. Sửa nhỏ ngoài module: `Supplier::purchaseReceipts()` + chặn xoá Supplier đã có phiếu nhập (`SupplierRepository::isUsedByPurchaseReceipt`, `SupplierService::delete`). Sidebar: thêm mục "Phiếu nhập kho"; các mục trỏ tới module chưa tồn tại (Khách hàng, Báo cáo, Cài đặt) tạm ẩn.

### Verification

`tests/Feature/PurchaseReceipt`: 13 passed. `tests/Feature/Inventory`: 59 passed. Full suite: 73 passed, 1 failed (`Tests\Feature\ExampleTest`, `/` trả 302 thay vì 200 - known scaffold issue, không liên quan Phase I).

### Known Issues / Technical Debt (không xử lý trong Phase I)

- `Tests\Feature\ExampleTest` fail (xem trên).
- `SupplierRepository::paginate()` dùng `withCount('products')` và `isUsedByProduct()` gọi `$supplier->products()`, nhưng `Supplier::products()` đang bị comment trong model - cần kiểm tra trang Nhà cung cấp.
- Legacy middleware `manager` (Category/Brand/Supplier/Unit/Product) chưa chuẩn hoá sang `permission:`.
- `CLAUDE.md` còn ghi VS2 trong khi repo là VS3; `DemoUserSeeder` đang bị comment trong `DatabaseSeeder`.
- Chưa có route/UI sửa hoặc xoá phiếu nháp (Service `updateDraft()` đã có và đã test).

### Not Done (ngoài phạm vi Phase I)

Purchase Order, Sales/Customer, Transfer, Dashboard/Reports, Barcode/QR, Low stock, API, FEFO, valuation/payment/invoice.

### Affected Documents

`docs/ai/current-phase.md`. Chưa có tài liệu database/business riêng cho Purchase Receipt (có thể bổ sung ở phase chuẩn hoá tài liệu).


---

## 2026-10-01 - Phase I.1: Stabilization sau Purchase Receipt

### Decision

Chỉ kiểm tra và xử lý các vấn đề phát hiện sau Phase I, không thêm module nghiệp vụ mới.

### Fixes

- `Supplier::products()` (hasMany theo `products.supplier_id`) được khôi phục, trước đó bị comment trong khi `SupplierRepository::paginate()` (`withCount('products')`), `SupplierRepository::isUsedByProduct()` và trang Suppliers/Index (`products_count`) vẫn dùng. Không đổi schema, không đổi business logic Product/Supplier.
- Thêm `tests/Feature/Supplier/SupplierRelationshipTest.php` (5 test): quan hệ products, `products_count` ở repository, chặn xoá Supplier đang có sản phẩm, chặn xoá Supplier đã có phiếu nhập, cho phép xoá Supplier không dùng.
- Sidebar: ẩn tạm các mục trỏ tới route chưa tồn tại (Khách hàng, Báo cáo, Cài đặt) và không render khung footer khi `adminMenuFooter` rỗng.

### Impact

Inventory business logic không đổi. Purchase Receipt không đổi.

### Verification

`tests/Feature/Supplier`: 5 passed. `tests/Feature/PurchaseReceipt`: 13 passed. `tests/Feature/Inventory`: 59 passed. Full suite: 78 passed, 1 failed (`Tests\Feature\ExampleTest`, `/` trả 302 thay vì 200 - known scaffold issue, không liên quan).

### Remaining Technical Debt

- Chưa có UI sửa/xoá phiếu nhập nháp (`PurchaseReceiptService::updateDraft()` đã có và đã test, chưa có route/trang).
- Legacy middleware `manager` trên Category/Brand/Supplier/Unit/Product chưa chuẩn hoá sang `permission:`.
- `Tests\Feature\ExampleTest` fail do `/` redirect (302).
- `CLAUDE.md` ghi VS2 trong khi repo hiện tại là VS3.
- `DemoUserSeeder` đang bị comment trong `DatabaseSeeder`.
- Các module Customer, Reports, Settings chưa tồn tại (menu đang ẩn).

### Note

`docs/ai/current-phase.md` KHÔNG được đổi sang Phase tiếp theo.


---

## 2026-10-02 - Phase J Complete: Customer Master Data + CustomerAccount Foundation

### Decision

Hệ thống vẫn là back-office/warehouse management: khách hàng KHÔNG đăng nhập vào đây. Phase J xây:

- `customers`: customer_code (unique), name, phone (nullable unique), email (nullable unique), customer_type (`individual|business|other`), address, note.
- `customer_accounts`: nền tảng dữ liệu tài khoản cho Ecommerce tương lai - customer_id (unique, FK restrictOnDelete), email (unique), password_hash (cast `hashed`), email_verified_at (nullable), status (`active|inactive`).
- Quan hệ: Customer hasOne CustomerAccount (0..1). Customer tồn tại độc lập không cần account. SalesDocument tương lai sẽ dùng `customer_id` nullable, không phụ thuộc CustomerAccount.
- Back-office: CRUD Customer (Index/Form/Show) + tạo account và đổi trạng thái active/inactive trong trang Show.
- Permission: `customer.view|create|update|delete` (admin, manager). Quản lý account dùng `customer.update`.

> Customer Account được thiết kế độc lập với admin `users` để chuẩn bị cho website bán hàng trong tương lai.

### Reason

Chuẩn bị master data khách hàng cho phase bán hàng (SalesDocument) và nền tảng danh tính cho website Ecommerce sau này, không phá authentication hiện tại.

### Impact

Không sửa Inventory Core. Không sửa authentication/RBAC của admin. Không có guard, route, API, UI đăng nhập/portal cho khách (có test `no customer login portal or api exists`). Customer đã có account không xoá được (không cascade).
Phiên bản Phase J đầu tiên (guard `customer` + portal đăng nhập) đã bị loại bỏ vì sai phạm vi; toàn bộ code, config và route liên quan đã được gỡ.

### Verification

`tests/Feature/Customer`: 33 passed (151 assertions). Full suite: 111 passed, 1 failed (`Tests\Feature\ExampleTest`, `/` trả 302 thay vì 200 - known scaffold issue). `npm run build`: thành công.

### Remaining Technical Debt

- Public `/register` tự cấp tài khoản `staff` và đăng nhập vào khu admin (cần quyết định đóng hay giới hạn).
- Quản lý CustomerAccount dùng chung quyền `customer.update` (chưa tách permission riêng).
- Chưa có chức năng xoá/đổi mật khẩu CustomerAccount; chưa có luồng xác minh email.
- Cột `password_hash` khác tên mặc định `password` của Laravel: khi làm xác thực Ecommerce cần override `getAuthPasswordName()`.
- `ExampleTest` fail (302); legacy middleware `manager`; `CLAUDE.md` ghi VS2; `DemoUserSeeder` đang comment.

### Not Implemented

SalesDocument, Sales Order, Ecommerce website/API, Customer login/portal/dashboard, order history, password reset, email verification workflow.

### Note

`docs/ai/current-phase.md` KHÔNG được đổi sang Phase tiếp theo.


---

## 2026-10-03 - Phase K Complete: SalesDocument / Sales Issue

### Decision

Xây chứng từ bán hàng back-office (không phải Ecommerce Order):

- `sales_documents`: document_code (unique, dạng `SD-000001`), customer_id (nullable, FK restrictOnDelete), warehouse_id (FK restrictOnDelete), status (`draft|posted`), document_date, note, created_by, posted_at.
- `sales_document_items`: sales_document_id, product_id, quantity (decimal 15,3), unit_price (decimal 15,2, snapshot từ `Product.selling_price` tại thời điểm tạo dòng, không đọc lại khi POST).
- Workflow `DRAFT -> POSTED`. DRAFT sửa/xoá được; POSTED chỉ đọc, không POST lần hai. Không có Cancel/Reverse.
- Khi POST, `SalesDocumentService` gọi `InventoryService::issueStock()` cho từng dòng trong một transaction, kèm `reference_type = sales_document`, `reference_id`. FIFO, StockAllocation và OUT StockMovement do InventoryService tạo, không duplicate.
- Một dòng thiếu tồn thì rollback toàn bộ (Stock, StockLot, StockMovement, StockAllocation, status, posted_at). Cho phép nhiều dòng cùng Product; tổng vượt tồn thì rollback cả phiếu. Lỗi thiếu tồn được bọc kèm số dòng và SKU.
- Row lock (`findForUpdate`) trên SalesDocument chống POST đồng thời / POST lần hai.
- Tổng tiền tính từ items (không lưu cột total riêng).
- Permission `sales-document.view|create|update|delete|post` (admin, manager). Route `/admin/sales` (`admin.sales.*`). Sidebar: mục "Bán hàng". UI: Index, Form (Create/Edit), Show.
- Sửa nhỏ ở module cũ (chỉ cộng thêm): `ProductRepository::options()` thêm `selling_price` và `sellingPrices()`, `CustomerRepository::options()`, `Customer::salesDocuments()` và guard chặn xoá Customer đã có chứng từ bán.

### Reason

Hoàn thiện chiều xuất kho của hệ thống, đối xứng với Purchase Receipt (Phase I), dùng Customer master (Phase J).

### Impact

`InventoryService`, FIFO, locking, quy ước dấu số lượng không đổi. Authentication admin không đổi. Không có Ecommerce/API/payment/shipping. Customer đã có chứng từ bán không xoá được.

### Verification

Theo xác nhận của project owner (2026-10-03): `tests/Feature/SalesDocument`, `tests/Feature/Inventory`, `tests/Feature/PurchaseReceipt`, `tests/Feature/Customer`, `tests/Feature/Supplier` và full suite đều pass, đã kiểm tra giao diện/chức năng. Số liệu: [điền từ output]. Full suite: [điền; known `ExampleTest` (`/` trả 302) nếu còn]. `npm run build`: [điền].

### Remaining Technical Debt

- Public `/register` tự cấp tài khoản `staff` và đăng nhập vào khu admin.
- Xoá Warehouse đã có chứng từ nhập/bán chỉ bị chặn bởi FK (lỗi 500), chưa có guard thân thiện.
- Chưa có Cancel/Reverse chứng từ POSTED.
- Form bán hàng chưa hiển thị tồn khả dụng; thiếu tồn chỉ phát hiện khi POST.
- Test chống POST đồng thời chỉ xác nhận có gọi row lock (SQLite không mô phỏng được race thật).
- Quản lý CustomerAccount dùng chung quyền `customer.update`.
- `StockLot` chưa có giá vốn nên chưa tính được lợi nhuận.
- `ExampleTest` fail (302); `CLAUDE.md` ghi VS2; `current-phase.md` ghi sai phase.

### Not Implemented

Payment, shipping, ecommerce/customer API, quotation, discount/tax, reservation, partial delivery, COGS/valuation, cancel/reverse.

### Note

`docs/ai/current-phase.md` KHÔNG được đổi sang Phase tiếp theo.


---

## 2026-10-04 - Phase M Complete: Stocktake / Inventory Count

### Decision

Xây chứng từ kiểm kê nằm phía trên Inventory Core:

- `stocktakes` (stocktake_code unique dạng `SK-000001`, warehouse_id, status `draft|posted`, stocktake_date, note, created_by NOT NULL, posted_at) và `stocktake_items` (stocktake_id, product_id, `system_quantity` snapshot, `actual_quantity` >= 0, `difference = actual - system`; UNIQUE(stocktake_id, product_id)). Quantity decimal(15,3). FK `restrictOnDelete`.
- Workflow `DRAFT -> POSTED`. DRAFT sửa/xoá được; POSTED chỉ đọc, không POST lần hai. Không có Cancel/Reverse/Approval.
- Snapshot: tồn hệ thống được chụp khi lưu phiếu nháp (tạo/sửa); sản phẩm chưa có Stock thì snapshot = 0 và KHÔNG tạo Stock. Lưu lại phiếu nháp là hành động chủ động chụp lại snapshot; POST không bao giờ tự cập nhật snapshot.
- POST trong một transaction: khóa phiếu -> khóa Stock các dòng theo `product_id` tăng dần -> kiểm tra stale cho TẤT CẢ dòng (tồn hiện tại khác `system_quantity` thì REJECT toàn bộ, chưa điều chỉnh gì) -> gọi `InventoryService::adjustStock(actual)` cho dòng có chênh lệch (meta `reference_type = stocktake`, `reference_id`, `user_id`) -> đánh dấu POSTED. Dòng không chênh lệch không gọi adjustStock, không tạo ADJUSTMENT 0 và không tạo Stock rỗng. Lỗi bất kỳ thì rollback toàn bộ.
- Adjustment tăng/giảm dùng nguyên logic Phase F (tăng tạo lô mới, giảm consume FIFO, không tạo StockAllocation, giữ lô về 0).
- Trang Show cảnh báo sớm các dòng đã stale ở phiếu nháp (chỉ đọc, hiển thị tồn hiện tại).
- Permission `stocktake.view|create|update|delete|post` (admin, manager). Route `/admin/stocktake` (`admin.stocktake.*`, 8 route). UI: Index, Form (Create/Edit), Show. Sidebar: mục "Phiếu kiểm kê" (mục "Kiểm kê" cũ trỏ trang điều chỉnh trực tiếp được giữ nguyên).

### Reason

Hoàn thiện nhóm chứng từ nghiệp vụ trên Inventory Core: PurchaseReceipt (receive), SalesDocument (issue), StockTransfer (transfer), Stocktake (adjust). Snapshot + phát hiện stale ngăn điều chỉnh sai khi tồn đã đổi giữa lúc lập phiếu và lúc chốt.

### Impact

`InventoryService` KHÔNG thay đổi ở Phase M. Stocktake chỉ ĐỌC/KHÓA Stock qua `StockRepository` (`findByWarehouseAndProduct`, `getForUpdate`) để chụp snapshot và kiểm tra stale; mọi thay đổi tồn đi qua `adjustStock()`. Authentication/RBAC không đổi.

### Verification

`tests/Feature/Stocktake`: 41 passed (326 assertions). `tests/Feature/Inventory`: 59 passed (926). `PurchaseReceipt`: 13 passed (107). `SalesDocument`: 36 passed (258). `StockTransfer`: 37 passed (328). `Customer`: 33 passed (151). `Supplier`: 5 passed (8). Full suite: 225 passed, 1 failed (2106 assertions) - failure duy nhất là `Tests\Feature\ExampleTest` (`/` trả 302 thay vì 200, known scaffold issue, không liên quan). `npm run build`: thành công (710 modules, 5.25s). Kiểm tra giao diện thủ công: đã kiểm tra, các flow tạo/sửa/xoá nháp, POST thiếu/thừa/không chênh lệch, stale snapshot và POSTED chỉ đọc đều hoạt động đúng.

### Remaining Technical Debt

- Trang điều chỉnh trực tiếp cũ (`/admin/inventory/adjustment`) vẫn tồn tại và điều chỉnh tồn không qua chứng từ kiểm kê.
- Chưa có Cancel/Reverse phiếu kiểm kê đã POST.
- Chưa có thao tác nạp toàn bộ sản phẩm có tồn trong kho vào phiếu kiểm kê (hiện nhập từng sản phẩm).
- Chưa có nút "Làm mới snapshot" riêng (hiện lưu lại phiếu nháp sẽ chụp lại).
- Test khóa đồng thời chỉ xác nhận có row lock và thứ tự khóa (SQLite không mô phỏng được race thật).
- Nợ cũ: public `/register`, quyền quản lý CustomerAccount dùng chung `customer.update`, `ExampleTest` (302), `CLAUDE.md` ghi VS2, `DemoUserSeeder` đang comment, `current-phase.md` ghi sai phase.

### Not Implemented

Cancel/Reverse, approval, khóa toàn bộ kho khi kiểm kê, barcode, app kiểm kê di động/offline, import Excel, serial, FEFO, giá vốn/valuation, cycle count, reservation, dashboard, reports, low-stock, Ecommerce API.

### Note

`docs/ai/current-phase.md` KHÔNG được đổi sang Phase tiếp theo.


---

## 2026-10-06 - Phase N Complete: Inventory Dashboard & Stock Monitoring

### Decision

Thêm lớp quan sát tồn kho, chỉ đọc, trên Inventory Core:

- `products.minimum_stock` decimal(15,3) default 0 (form Product Create/Edit, validation numeric >= 0, tối đa 3 chữ số thập phân; để trống khi tạo thì dùng 0, khi sửa thì giữ nguyên giá trị cũ).
- Inventory Overview: nâng cấp trang `/admin/stock` (route và quyền `stock.view` hiện có, không tạo route mới). Hiển thị Product x Warehouse với tồn, tổng tồn sản phẩm, số lô còn hàng, mức tối thiểu và trạng thái. Filter: tìm SKU/tên, kho, danh mục, thương hiệu, trạng thái; phân trang.
- Trạng thái tính runtime, không lưu DB: `OUT_OF_STOCK` (tồn = 0), `LOW` (0 < tồn < minimum_stock), `NORMAL` (tồn > 0 và >= minimum_stock; minimum_stock = 0 thì còn hàng là NORMAL).
- Dashboard `/admin/dashboard`: KPI (sản phẩm đang hoạt động, kho hoạt động, tổng tồn từ `stocks`, số sản phẩm hết hàng và tồn thấp theo TỔNG tồn trên mọi kho), số chứng từ nháp của 4 loại, 10 hoạt động kho gần nhất từ `stock_movements` (kèm mã chứng từ tham chiếu).
- Dashboard vẫn mở cho mọi người dùng đã đăng nhập (là đích redirect khi bị từ chối quyền); số liệu kho chỉ được trả khi có `stock.view`.
- Tầng đọc: `InventoryOverviewRepository`, `InventoryDashboardRepository`, `InventoryMonitoringService`, `App\Support\StockStatus`.

### Reason

Giúp người quản lý nhìn được tình trạng kho hiện tại, không chỉ ghi nhận giao dịch.

### Impact

`InventoryService`, FIFO, Stock/StockLot/StockMovement/StockAllocation không đổi. Không có bảng mới ngoài cột `products.minimum_stock`; không thêm permission mới (dùng `stock.view`). Trạng thái ở Overview tính theo từng dòng kho, trong khi KPI hết hàng/tồn thấp tính theo tổng tồn sản phẩm. Overview ở chế độ chọn kho liệt kê mọi sản phẩm hoạt động (sản phẩm chưa có tồn ở kho đó hiện 0, Hết hàng); sản phẩm ngừng hoạt động chỉ hiện khi còn tồn.

### Verification

`tests/Feature/Monitoring`: 29 passed (151 assertions). Hồi quy Inventory 59, PurchaseReceipt 13, SalesDocument 36, StockTransfer 37, Stocktake 41 đều pass. Customer 33 và Supplier 5: [điền kết quả sau Phase N]. Full suite: [điền; mong đợi chỉ còn `ExampleTest` (302)]. `npm run build`: thành công (710 modules). Kiểm tra giao diện thủ công: Test giao diện thành công.

### Remaining Technical Debt

- `StockRepository::paginate()` và `StockService::list()` không còn được controller sử dụng (giữ nguyên, chưa dọn).
- Route Product vẫn dùng middleware `manager` kiểu cũ (chưa chuẩn hóa sang `permission:`).
- Chưa có Reports, Settings; chưa có đề xuất đặt hàng từ tồn thấp.
- Nợ cũ: public `/register`, `ExampleTest` (302), quyền quản lý CustomerAccount dùng chung `customer.update`, trang điều chỉnh trực tiếp `/admin/inventory/adjustment`, `CLAUDE.md` ghi VS2, `current-phase.md` ghi sai phase.

### Not Implemented

Purchase Order, reorder/forecast, report engine, snapshot/cache dashboard, biểu đồ, Cancel/Reverse, giá vốn, API, Ecommerce, barcode, serial, FEFO, thông báo.

### Note

`docs/ai/current-phase.md` KHÔNG được đổi sang Phase tiếp theo.