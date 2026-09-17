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
