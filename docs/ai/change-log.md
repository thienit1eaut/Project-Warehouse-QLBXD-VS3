# AI / Architecture Change Log

Tài liệu này ghi nhận các thay đổi quan trọng về architecture, business rule và AI workflow.

Không cần ghi mọi bug fix nhỏ.

Chỉ ghi các thay đổi có ảnh hưởng đến:

* Architecture
* Database design
* Business rules
* Inventory behavior
* AI development workflow
* Phase decisions

---

## Format

```text
## YYYY-MM-DD - Title

### Decision

...

### Reason

...

### Impact

...

### Affected Documents

...
```

---

## 2026-09-16 - Inventory Architecture Baseline

### Decision

Xác nhận inventory architecture gồm:

```text
Product
Warehouse
Stock
StockLot
StockMovement
StockAllocation
InventoryService
```

### Reason

Tách rõ:

```text
Product
→ master data

Stock
→ current quantity

StockLot
→ receipt-origin quantity

StockMovement
→ inventory history

StockAllocation
→ outbound traceability
```

### Impact

Inventory quantity không được đặt trong Product.

StockLot được sử dụng để hỗ trợ FIFO.

StockAllocation được sử dụng để truy vết OUT movement về StockLot.

Inventory mutations phải đi qua InventoryService.

### Core Invariants

```text
Stock.quantity_on_hand >= 0

0 <= StockLot.quantity_remaining
   <= StockLot.quantity_received

Stock.quantity_on_hand
=
SUM(StockLot.quantity_remaining)

SUM(StockAllocation.quantity)
=
OUT movement quantity
```

### FIFO

FIFO ordering:

```text
received_at ASC
id ASC
```

### Transaction

Receive, Issue và Adjustment phải transactional.

### Future Extension

Có thể mở rộng:

* expiry date
* FEFO
* serial tracking
* commercial inventory features

nhưng không đưa các tính năng này vào scope hiện tại nếu chưa được yêu cầu.

---

## 2026-09-22 - Phase A Complete + Phase B StockLot Foundation

### Decision

Phase A (Foundation/Database correction) đã hoàn tất và được project owner verify trực tiếp qua Tinker trên database thật (migrate:fresh --seed thành công, đầy đủ luồng increaseStock/decreaseStock/adjustStock hoạt động đúng invariant).

Phase B (StockLot foundation) đã triển khai: migration `stock_lots`, Model `StockLot`, relationships `Product::stocks()/stockLots()`, `Warehouse::stockLots()`, `Stock::stockLots()`, `StockLot::stock()/warehouse()/product()`, và `StockLotRepository` (persistence/query only).

### Reason

Theo đúng roadmap 8 phase (A→H) đã chốt ngày 2026-09-18 để đưa code Inventory hiện tại (Stock+StockMovement đơn giản) lên đúng Locked Inventory Architecture đã xác nhận ngày 2026-09-16.

### Impact

`InventoryService` giữ nguyên KHÔNG đổi — Phase B chỉ là schema/model/repository foundation, chưa tích hợp tạo/tiêu thụ StockLot vào business logic. FIFO issue, StockAllocation, Receive integration, Adjustment integration đều CHƯA triển khai — thuộc Phase C/D/E/F.

### Affected Documents

`docs/database/inventory-database.md` (schema `stock_lots` nay đã có trong code, khớp thiết kế tài liệu), `docs/architecture/inventory-module.md`.

### Note

`docs/ai/current-phase.md` KHÔNG được cập nhật ở lần này — file tự quy định "AI không được tự thay đổi file này để chuyển phase", và nội dung file này đang track một roadmap Phase 0→1 khác (audit → Product Module) không phải roadmap Phase A→H của Inventory. Việc cập nhật file này để phản ánh đúng tiến độ Inventory cần project owner quyết định.

---

## 2026-09-29 - Phase C→H Complete: Inventory Core + HTTP/Inertia Integration

### Decision

Toàn bộ roadmap Inventory Phase C→H đã hoàn tất và được verify:

- Phase C (Receive): `InventoryService::receiveStock()` tạo StockLot mới mỗi lần nhập, `increaseStock()` trở thành alias.
- Phase D (Issue + FIFO): `InventoryService::issueStock()` consume StockLot theo FIFO (received_at ASC, id ASC) qua `StockLotRepository::lockEligibleForIssue()`/`decrementRemaining()`, `decreaseStock()` trở thành alias.
- Phase E (StockAllocation): migration/Model/Repository `StockAllocation`, `issueStock()` ghi lại allocation cho từng lot thực consume sau khi tạo StockMovement OUT.
- Phase F (Adjustment sync): `adjustStock()` đồng bộ StockLot — tăng tạo lot mới, giảm consume FIFO, giữ invariant `Stock.quantity_on_hand = SUM(StockLot.quantity_remaining)`.
- Phase G (Regression suite): `InventoryRegressionTest.php` (16 test) verify full lifecycle, isolation Product/Warehouse, transaction rollback xuyên nhiều bước.
- Phase H (HTTP/Inertia integration): `InventoryController` (Admin) + `ReceiveStockRequest`/`IssueStockRequest`/`AdjustStockRequest` + routes `admin.inventory.*` (permission `stock.receive`/`stock.issue`/`stock.adjust` mở rộng trên module `stock` có sẵn) + 4 trang Vue (`Admin/Inventory/Index|Receive|Issue|Adjustment.vue`) + `InventoryHttpTest.php` (10 test qua HTTP layer thật).

### Reason

Hoàn thành đúng roadmap 8 phase (A→H) đã chốt ngày 2026-09-18 để đưa Inventory lên đúng Locked Architecture đã xác nhận ngày 2026-09-16.

### Impact

Inventory Core (`InventoryService`, FIFO, StockAllocation, Adjustment) giữ nguyên không đổi ở Phase H — chỉ thêm tầng HTTP (Controller/FormRequest/Routes/Vue) gọi vào Service đã có sẵn. Sidebar (`useAdminMenu.js`) đã bật lại 3 mục Nhập kho/Xuất kho/Kiểm kê trỏ đúng route thật (trước đó bị comment tạm từ khi phát hiện 404). Mục "Chuyển kho" (transfer) vẫn tạm ẩn — ngoài phạm vi Locked Architecture hiện tại.

### Affected Documents

`docs/architecture/inventory-module.md`, `docs/database/inventory-database.md`, `docs/business/inventory-rules.md`, `docs/business/fifo-rules.md`, `docs/business/stock-allocation.md` — toàn bộ đã khớp với implementation thật.

### Note

`docs/ai/current-phase.md` vẫn KHÔNG được cập nhật (lý do như ghi chú Phase A/B ở trên) — vẫn cần project owner quyết định.