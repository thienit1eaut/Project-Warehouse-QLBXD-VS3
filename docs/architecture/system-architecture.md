# System Architecture

## 1. Project

**Project:** DATN2026-VS2
**Tên:** Đồ án quản lý kho

Hệ thống được xây dựng nhằm quản lý sản phẩm, kho hàng và quá trình nhập/xuất/điều chỉnh tồn kho.

Phạm vi hiện tại tập trung vào các sản phẩm như:

* Xe đạp
* Phụ tùng
* Phụ kiện
* Săm/lốp
* Xích
* Phanh
* Các linh kiện liên quan

Một số sản phẩm hiện tại không có hạn sử dụng.

Thiết kế vẫn cho phép mở rộng sang các sản phẩm có hạn sử dụng trong tương lai.

---

## 2. Nguyên tắc kiến trúc

Hệ thống phân tách:

```text
Product
    ↓
Master Data

Warehouse
    ↓
Physical / Logical Location

Stock
    ↓
Current Inventory Balance

StockLot
    ↓
Quantity Originating From A Receipt

StockMovement
    ↓
Inventory History / Audit

StockAllocation
    ↓
Outbound Traceability

InventoryService
    ↓
Inventory Business Boundary
```

---

## 3. Product không quản lý tồn kho

`Product` chỉ chứa thông tin master data của sản phẩm.

Product không được chứa:

```text
quantity
quantity_on_hand
stock_quantity
inventory_quantity
```

Số lượng tồn kho được xác định theo:

```text
Product + Warehouse
```

thông qua `Stock`.

---

## 4. Warehouse

`Warehouse` đại diện cho một kho hàng.

Một Product có thể tồn tại ở nhiều Warehouse.

Ví dụ:

```text
Product A
    ├── Warehouse 1 → 50
    └── Warehouse 2 → 30
```

---

## 5. Stock

`Stock` biểu diễn tổng số lượng hiện tại của một Product tại một Warehouse.

Khóa logic:

```text
warehouse_id + product_id
```

Stock là aggregate/current balance.

Stock không thay thế StockLot và StockMovement.

---

## 6. StockLot

`StockLot` đại diện cho lượng hàng có cùng nguồn gốc từ một lần nhập hàng.

Một lần receipt tạo một StockLot mới.

StockLot không nhất thiết có nghĩa là "batch sản xuất".

Nó được sử dụng chủ yếu để:

* Theo dõi nguồn gốc lượng hàng nhập.
* Hỗ trợ FIFO.
* Theo dõi quantity_remaining.
* Hỗ trợ traceability khi xuất kho.

`expiry_date` có thể nullable để hỗ trợ mở rộng trong tương lai.

---

## 7. StockMovement

`StockMovement` lưu lịch sử thay đổi tồn kho.

Các loại chính:

```text
IN
OUT
ADJUSTMENT
```

StockMovement trả lời câu hỏi:

> Tồn kho đã thay đổi như thế nào?

---

## 8. StockAllocation

`StockAllocation` liên kết một movement OUT với các StockLot đã bị tiêu thụ.

Một movement OUT có thể sử dụng nhiều StockLot.

Ví dụ:

```text
OUT = 25

Lot 1 → 20
Lot 2 → 5
```

Khi đó:

```text
StockAllocation
├── Lot 1 → 20
└── Lot 2 → 5
```

---

## 9. InventoryService

`InventoryService` là business boundary của inventory.

Các nghiệp vụ inventory phải đi qua service này.

Các nghiệp vụ chính:

```text
receive()
issue()
adjust()
```

Controller/UI không được trực tiếp thay đổi Stock hoặc StockLot.

---

## 10. Transaction Boundary

Các nghiệp vụ làm thay đổi inventory phải được thực hiện trong database transaction.

Bao gồm:

* Receive
* Issue
* Adjustment

Nếu bất kỳ bước nào thất bại, toàn bộ thay đổi phải rollback.

---

## 11. Concurrency

Inventory mutation phải xem xét concurrency.

Ví dụ:

```text
Stock = 10

Transaction A → issue 7
Transaction B → issue 5
```

Không được để cả hai transaction cùng đọc `10` rồi cùng commit.

Phải sử dụng cơ chế locking phù hợp để bảo vệ inventory state.

---

## 12. Kiến trúc logic

Luồng tổng quát:

```text
Controller
    ↓
Application / Use Case
    ↓
InventoryService
    ↓
Repository / Persistence
    ↓
Database
```

Controller không chứa business logic phức tạp.

UI không thực hiện FIFO.

Database là nơi persistence và constraint được đảm bảo.

Service là nơi business rule được điều phối.

---

## 13. Mục tiêu thiết kế

Kiến trúc phải:

* Dễ hiểu.
* Dễ test.
* Dễ mở rộng.
* Có transaction boundary rõ ràng.
* Có audit history.
* Có traceability.
* Hỗ trợ FIFO.
* Có khả năng mở rộng FEFO/expiry trong tương lai.
* Không over-engineering theo mô hình ERP/microservice khi project hiện tại chưa cần.
