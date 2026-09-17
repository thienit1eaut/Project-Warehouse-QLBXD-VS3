# Inventory Module

## 1. Mục đích

Inventory Module chịu trách nhiệm quản lý số lượng hàng tồn kho và lịch sử thay đổi tồn kho.

---

## 2. Thành phần

Inventory Module gồm các thành phần chính:

```text
Warehouse
Stock
StockLot
StockMovement
StockAllocation
InventoryService
```

---

## 3. Warehouse

Đại diện cho kho hàng.

```text
Warehouse 1
Warehouse 2
...
```

---

## 4. Stock

Stock biểu diễn số lượng hiện tại của một Product tại một Warehouse.

```text
Stock
├── warehouse_id
├── product_id
└── quantity_on_hand
```

Logic uniqueness:

```text
UNIQUE(warehouse_id, product_id)
```

---

## 5. StockLot

StockLot biểu diễn lượng hàng còn lại từ một lần nhập.

```text
StockLot
├── warehouse_id
├── product_id
├── quantity_received
├── quantity_remaining
├── received_at
└── expiry_date (nullable)
```

Mỗi receipt tạo một StockLot mới.

---

## 6. StockMovement

StockMovement lưu inventory history.

Các loại:

```text
IN
OUT
ADJUSTMENT
```

Movement phải ghi nhận đầy đủ thông tin cần thiết để audit.

---

## 7. StockAllocation

StockAllocation ghi nhận quantity của một OUT movement được lấy từ StockLot nào.

Quan hệ:

```text
StockMovement (OUT)
        │
        ├── StockAllocation → StockLot
        ├── StockAllocation → StockLot
        └── ...
```

---

## 8. InventoryService

Mọi inventory mutation phải đi qua InventoryService.

### Receive

```text
InventoryService.receive()
```

Thực hiện:

1. Validate input.
2. Lock/prepare required inventory state.
3. Create/update Stock.
4. Create StockLot.
5. Create IN StockMovement.
6. Commit transaction.

### Issue

```text
InventoryService.issue()
```

Thực hiện:

1. Validate input.
2. Lock Stock.
3. Kiểm tra sufficient stock.
4. Lấy các StockLot phù hợp.
5. Áp dụng FIFO.
6. Giảm quantity_remaining.
7. Create StockAllocation.
8. Create OUT StockMovement.
9. Update Stock.
10. Commit transaction.

### Adjustment

```text
InventoryService.adjust()
```

Thực hiện adjustment thông qua movement.

Không được sửa quantity một cách tùy tiện ngoài business flow.

---

## 9. Business boundary

Không được:

```text
Controller → UPDATE stocks
Controller → UPDATE stock_lots
UI → UPDATE inventory
```

Phải:

```text
Controller
    ↓
InventoryService
    ↓
Repository
    ↓
Database
```

---

## 10. Core invariant

Luôn duy trì:

```text
Stock.quantity_on_hand
=
SUM(StockLot.quantity_remaining)
```

theo:

```text
warehouse_id + product_id
```

---

## 11. Không over-engineering

Project hiện tại chưa yêu cầu:

* Microservices.
* Event sourcing.
* Distributed inventory.
* ERP-level warehouse management.
* Serial number tracking.
* Complex batch management.

Chỉ triển khai những gì cần thiết cho phạm vi hiện tại nhưng thiết kế đủ sạch để mở rộng sau này.
