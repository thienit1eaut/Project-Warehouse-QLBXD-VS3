# Inventory Database Design

## 1. Tổng quan

Các bảng chính:

```text
products
warehouses
stocks
stock_lots
stock_movements
stock_allocations
```

Tên bảng/cột thực tế phải nhất quán với migration hiện tại.

---

## 2. products

Chứa master data của sản phẩm.

Không chứa current inventory quantity.

---

## 3. warehouses

Chứa thông tin warehouse.

Một warehouse có nhiều Stock và StockLot.

---

## 4. stocks

Đại diện current inventory balance.

Conceptual structure:

```text
stocks
---------
id
warehouse_id
product_id
quantity_on_hand
created_at
updated_at
```

Constraint:

```text
UNIQUE(warehouse_id, product_id)
```

Business invariant:

```text
quantity_on_hand >= 0
```

---

## 5. stock_lots

Đại diện lượng hàng phát sinh từ một receipt.

Conceptual structure:

```text
stock_lots
------------
id
warehouse_id
product_id
quantity_received
quantity_remaining
received_at
expiry_date
created_at
updated_at
```

Constraints:

```text
quantity_received > 0

0 <= quantity_remaining
quantity_remaining <= quantity_received
```

`expiry_date` có thể NULL.

---

## 6. stock_movements

Lưu lịch sử inventory mutation.

Conceptual structure:

```text
stock_movements
-----------------
id
warehouse_id
product_id
type
quantity
reference_type
reference_id
created_at
```

Types:

```text
IN
OUT
ADJUSTMENT
```

Các field bổ sung phụ thuộc implementation thực tế.

---

## 7. stock_allocations

Liên kết OUT movement với StockLot.

Conceptual structure:

```text
stock_allocations
-------------------
id
stock_movement_id
stock_lot_id
quantity
created_at
```

Constraint:

```text
quantity > 0
```

---

## 8. Important invariants

### Invariant 1

```text
Stock.quantity_on_hand >= 0
```

### Invariant 2

```text
0 <= StockLot.quantity_remaining
<= StockLot.quantity_received
```

### Invariant 3

Theo từng Product + Warehouse:

```text
Stock.quantity_on_hand
=
SUM(StockLot.quantity_remaining)
```

### Invariant 4

Đối với OUT movement:

```text
SUM(StockAllocation.quantity)
=
StockMovement.quantity
```

---

## 9. Indexing

Các query thường xuyên phải được index phù hợp.

Đặc biệt:

```text
stocks:
    warehouse_id + product_id
```

và StockLot query:

```text
warehouse_id
product_id
quantity_remaining
received_at
```

FIFO query cần hỗ trợ:

```text
WHERE warehouse_id = ?
AND product_id = ?
AND quantity_remaining > 0
ORDER BY received_at ASC, id ASC
```

Index thực tế phải được quyết định dựa trên database engine và execution plan nếu cần.

---

## 10. Foreign keys

Các quan hệ giữa:

```text
Product
Warehouse
Stock
StockLot
StockMovement
StockAllocation
```

phải được bảo vệ bằng foreign key khi phù hợp.

Không để orphan record nếu business model không cho phép.

---

## 11. Transaction

Các mutation liên quan đến nhiều bảng phải nằm trong cùng transaction.

Ví dụ Receive:

```text
Stock
StockLot
StockMovement
```

phải cùng commit hoặc cùng rollback.

Issue:

```text
Stock
StockLot
StockMovement
StockAllocation
```

phải cùng commit hoặc cùng rollback.
