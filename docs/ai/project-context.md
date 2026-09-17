# DATN2026-VS2 - AI Project Context

## 1. Project

Project name:

```text
DATN2026-VS2
```

Project type:

```text
Graduation Project - Warehouse Management System
```

Mục tiêu hiện tại là xây dựng một hệ thống quản lý sản phẩm và tồn kho có kiến trúc rõ ràng, dễ kiểm thử và có khả năng mở rộng.

---

## 2. Current Business Scope

Sản phẩm hiện tại chủ yếu gồm:

* Xe đạp
* Phụ tùng
* Phụ kiện
* Săm/lốp
* Xích
* Phanh
* Linh kiện liên quan

Một số sản phẩm không có hạn sử dụng.

Thiết kế có thể hỗ trợ `expiry_date` nullable để mở rộng trong tương lai.

---

## 3. Locked Inventory Architecture

Các entity chính:

```text
Product
Warehouse
Stock
StockLot
StockMovement
StockAllocation
InventoryService
```

Không tự ý thay đổi mô hình này.

Nếu implementation hiện tại khác architecture contract, phải báo conflict trước khi redesign.

---

## 4. Entity Responsibilities

### Product

Product = master data.

Product không chứa inventory quantity.

---

### Warehouse

Warehouse = physical/logical warehouse.

---

### Stock

Stock = current total quantity của một Product trong một Warehouse.

Logic identity:

```text
warehouse_id + product_id
```

---

### StockLot

StockLot = quantity originating from ONE receipt/inbound event.

StockLot không nhất thiết đại diện cho production batch.

Fields quan trọng:

```text
quantity_received
quantity_remaining
received_at
expiry_date nullable
```

---

### StockMovement

StockMovement = inventory change history.

Types:

```text
IN
OUT
ADJUSTMENT
```

---

### StockAllocation

StockAllocation = allocation của OUT movement vào StockLot.

Một OUT movement có thể consume nhiều StockLot.

---

### InventoryService

InventoryService = business boundary cho inventory mutation.

Inventory mutation phải đi qua InventoryService.

---

## 5. Core Invariants

### Stock

```text
Stock.quantity_on_hand >= 0
```

### StockLot

```text
quantity_received > 0
```

```text
0 <= quantity_remaining <= quantity_received
```

### Stock / StockLot

Theo từng Product + Warehouse:

```text
Stock.quantity_on_hand
=
SUM(StockLot.quantity_remaining)
```

### StockAllocation

Đối với OUT movement:

```text
SUM(StockAllocation.quantity)
=
StockMovement.quantity
```

---

## 6. FIFO

FIFO áp dụng cho outbound.

Eligible lot:

```text
warehouse_id matches
product_id matches
quantity_remaining > 0
```

Ordering:

```text
received_at ASC
id ASC
```

Không tự chuyển FIFO thành FEFO.

---

## 7. Receive

Mỗi receipt tạo một StockLot mới.

Receive phải:

```text
BEGIN TRANSACTION

update/create Stock
create StockLot
create IN StockMovement

COMMIT
```

Nếu fail:

```text
ROLLBACK
```

---

## 8. Issue

Issue phải:

```text
BEGIN TRANSACTION

lock required inventory state
check sufficient stock
select eligible lots
apply FIFO
reduce StockLot.quantity_remaining
create StockAllocation
create OUT StockMovement
update Stock
commit

```

Nếu fail:

```text
ROLLBACK
```

Không cho phép negative stock.

---

## 9. Adjustment

Adjustment phải được ghi nhận bằng movement.

Positive adjustment:

```text
delta > 0
```

có thể tạo adjustment lot để duy trì nguồn gốc quantity.

Negative adjustment:

```text
delta < 0
```

không được tùy tiện sửa một lot bất kỳ.

Chiến lược mặc định là FIFO nếu cần giảm quantity_remaining.

---

## 10. Concurrency

Inventory mutation phải xử lý concurrency.

Ví dụ:

```text
Stock = 10

Transaction A → issue 7
Transaction B → issue 5
```

Không được để cả hai transaction cùng đọc 10 và commit thành công.

Locking phải được thực hiện trong transaction phù hợp với database implementation.

---

## 11. Architecture Boundary

Không:

```text
Controller → directly mutate Stock
Controller → directly mutate StockLot
UI → calculate FIFO
UI → mutate inventory
```

Phải:

```text
Controller
    ↓
InventoryService
    ↓
Repository / Persistence
    ↓
Database
```

---

## 12. AI Working Rules

AI phải:

1. Đọc `docs/ai/project-context.md`.
2. Đọc `docs/ai/coding-rules.md`.
3. Đọc `docs/ai/current-phase.md`.
4. Đọc architecture/business docs liên quan.
5. Kiểm tra source code hiện tại trước khi sửa.
6. Kiểm tra migrations/models/services liên quan.
7. Không tự ý mở rộng scope.
8. Không tự ý redesign architecture.
9. Không rewrite working code nếu không cần.
10. Chạy test sau implementation nếu có thể.

---

## 13. Important Principle

Không coi AI-generated code là source of truth.

Source of truth phải được xác nhận thông qua:

```text
Architecture
+
Business Rules
+
Database Constraints
+
Tests
+
Actual Implementation
```

---

## 14. Current Development State

Chi tiết phase hiện tại được quản lý riêng tại:

```text
docs/ai/current-phase.md
```

Không tự suy đoán phase hiện tại nếu file này có thông tin khác.

---

## 15. Conflict Handling

Nếu source code hiện tại mâu thuẫn với architecture:

```text
STOP
```

Sau đó báo:

```text
1. Conflict là gì?
2. File nào bị ảnh hưởng?
3. Architecture hiện tại yêu cầu gì?
4. Code hiện tại đang làm gì?
5. Có những phương án nào?
6. Đề xuất phương án nào và tại sao?
```

Không silently redesign.

---

## 16. Project Philosophy

Ưu tiên:

```text
Simple
Explicit
Testable
Transactional
Traceable
Maintainable
Extensible
```

Tránh:

```text
Over-engineering
Unnecessary abstraction
Premature optimization
Unnecessary microservices
Complex ERP features outside scope
```
