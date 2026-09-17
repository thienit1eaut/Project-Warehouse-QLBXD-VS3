# Current Development Phase

## Phase

```text
PHASE 0
```

## Name

```text
Project Audit & Architecture Verification
```

## Status

```text
DESIGN / AUDIT ONLY
```

---

## Objective

Kiểm tra project hiện tại trước khi tiếp tục implementation.

Mục tiêu:

* Hiểu source code hiện tại.
* Hiểu database hiện tại.
* Xác định module đã có.
* Xác định phần đã hoàn thành.
* Phát hiện architecture conflict.
* Xác nhận inventory architecture.
* Xác nhận development scope.

---

## Allowed

AI được phép:

* Đọc source code.
* Đọc migrations.
* Đọc models/entities.
* Đọc services.
* Đọc repositories.
* Đọc controllers.
* Đọc tests.
* Phân tích architecture.
* Phân tích database.
* Phát hiện inconsistency.
* Đề xuất thay đổi.

---

## Not Allowed

AI không được:

* Rewrite code.
* Tự ý sửa database.
* Tự ý tạo migration.
* Tự ý triển khai FIFO.
* Tự ý thay đổi architecture.
* Tự ý thêm dependency.
* Tự ý refactor toàn project.

---

## Architecture To Verify

```text
Product
Warehouse
Stock
StockLot
StockMovement
StockAllocation
InventoryService
```

---

## Required Output

AI phải báo cáo:

### 1. Current project structure

Các module/file chính hiện tại.

### 2. Existing implementation

Những phần đã có.

### 3. Missing implementation

Những phần chưa có.

### 4. Architecture conflicts

Những điểm khác với architecture contract.

### 5. Database conflicts

Những điểm khác với database design.

### 6. Recommended next phase

Đề xuất phase tiếp theo nhưng không tự triển khai.

---

## Phase Completion Condition

Phase chỉ hoàn thành khi:

* Project structure đã được audit.
* Architecture conflict đã được xác định.
* Các conflict quan trọng đã được giải quyết hoặc chấp nhận.
* Current architecture đã được xác nhận.

---

## Next

```text
PHASE 1 - Product Module
```

---

## Important

AI không được tự thay đổi file này để chuyển phase.

Phase được chuyển bởi project owner/developer sau khi phase hiện tại được xác nhận hoàn thành.
