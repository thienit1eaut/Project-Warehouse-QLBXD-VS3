# AGENTS.md

## Project

**Đồ án quản lý kho (DATN2026-VS2)**

This repository is a graduation project for warehouse/inventory
management focused initially on bicycles, bicycle spare parts,
accessories, inner tubes/tires, chains, brakes, and components.

The system is intentionally designed so that inventory architecture can
later be extended for commercial use without prematurely adding
ERP-level complexity.

------------------------------------------------------------------------

## 1. Read These First

Before changing code, read:

1.  `docs/README.md`
2.  `docs/ai/project-context.md`
3.  `docs/ai/coding-rules.md`
4.  `docs/ai/current-phase.md`
5.  relevant architecture/database/business documents

Then inspect the actual implementation.

Documentation describes the intended architecture; code describes the
current implementation. When they conflict, do not silently choose one.
Report the conflict.

------------------------------------------------------------------------

## 2. Current Development Method

Work phase-by-phase.

The project owner decides when a phase is complete and when the project
moves to the next phase.

Do not implement future phases unless explicitly requested.

Preferred sequence:

``` text
Phase 0  Audit / Design
Phase 1  Product
Phase 2  Warehouse + Stock + StockLot
Phase 3  Inventory Core + StockMovement
Phase 4  Goods Receipt
Phase 5  FIFO + StockAllocation Design Gate
Phase 6  Goods Issue + FIFO + StockAllocation
Phase 7  Adjustment
Phase 8  History + Traceability
Phase 9  API + UI
Phase 10 Concurrency + Integrity Audit
Phase 11 Full Tests + Final Review
```

------------------------------------------------------------------------

## 3. Locked Domain Model

### Product

Master data only.

Never store current inventory quantity on Product.

### Warehouse

Represents a physical/logical warehouse.

### Stock

Current total quantity for one Product in one Warehouse.

### StockLot

Quantity originating from one receiving event.

Constraints:

``` text
quantity_received > 0
0 <= quantity_remaining <= quantity_received
```

`expiry_date` can be nullable.

Expiry/FEFO is a future capability, not the current default.

### StockMovement

Inventory audit/history.

Movement types:

``` text
IN
OUT
ADJUSTMENT
```

### StockAllocation

Maps an OUT movement to the StockLots consumed by that movement.

Invariant:

``` text
SUM(StockAllocation.quantity)
=
StockMovement.OUT.quantity
```

------------------------------------------------------------------------

## 4. Core Invariant

For every product and warehouse:

``` text
Stock.quantity_on_hand
=
SUM(StockLot.quantity_remaining)
```

Every inventory mutation must preserve this invariant.

------------------------------------------------------------------------

## 5. Inventory Boundary

Authoritative flow:

``` text
Controller / UI
       ↓
InventoryService
       ↓
Repository / Persistence
       ↓
Database
```

All inventory mutations must go through `InventoryService`.

Controllers must not:

-   perform FIFO
-   choose StockLots
-   update Stock
-   update StockLots
-   create inventory movements directly
-   implement transaction orchestration

UI must not contain authoritative inventory logic.

------------------------------------------------------------------------

## 6. FIFO

Current issue strategy is FIFO.

Eligible lots:

``` text
same warehouse
same product
quantity_remaining > 0
```

Order:

``` text
received_at ASC
id ASC
```

The ID is a deterministic tie-breaker.

Never silently replace FIFO with FEFO.

------------------------------------------------------------------------

## 7. Transactions and Concurrency

Receive, issue, and adjustment are transactional inventory operations.

### Receive

Atomically:

``` text
Stock
StockLot
StockMovement(IN)
```

### Issue

Atomically:

``` text
Stock
StockLot(s)
StockMovement(OUT)
StockAllocation(s)
```

### Adjustment

Atomically preserve inventory state and create the corresponding
adjustment movement.

Use row locking/appropriate database concurrency controls when inventory
can be modified concurrently.

Never allow two concurrent issues to both consume the same stock based
on stale reads.

------------------------------------------------------------------------

## 8. Architecture Rules

Respect separation of concerns:

### Controller

Request/response orchestration only.

### Service

Business logic and transaction boundary.

### Repository

Persistence/query responsibilities.

### UI

Presentation and user input.

Do not move business logic between layers merely for convenience.

------------------------------------------------------------------------

## 9. Database Rules

Before modifying schema:

-   inspect existing migrations
-   inspect current schema
-   inspect related models
-   check current phase
-   verify that the change belongs to the current task

Do not:

-   create duplicate structures
-   rename existing structures without approval
-   add unrelated fields
-   introduce unnecessary indexes
-   change schema outside the active phase

Preserve project naming conventions.

------------------------------------------------------------------------

## 10. Implementation Rules

Before editing:

-   inspect current code
-   inspect dependencies
-   inspect callers
-   inspect tests
-   inspect migrations when database behavior is involved

Then:

-   make the smallest coherent change
-   preserve working behavior
-   avoid unrelated refactors
-   reuse existing abstractions
-   follow existing conventions

Do not rewrite an entire module to implement a small feature.

------------------------------------------------------------------------

## 11. Dependency Rules

Do not add packages or libraries without explicit approval.

If a dependency seems necessary, report:

``` text
Why it is needed
What it replaces/solves
Alternatives
Impact on project
```

Wait for approval if it changes the dependency surface.

------------------------------------------------------------------------

## 12. Testing

Run tests relevant to every implementation.

For inventory functionality, test where applicable:

``` text
Receive
  → Stock increases
  → new StockLot exists
  → IN movement exists

Issue
  → FIFO selection
  → multi-lot consumption
  → Stock decreases
  → StockLot decreases
  → OUT movement exists
  → allocations exist

Integrity
  → Stock = SUM(StockLot.remaining)
  → allocation sum = OUT quantity
  → no negative quantity

Adjustment
  → positive adjustment
  → negative adjustment
  → adjustment movement

Failure
  → transaction rollback
  → no partial inventory state
```

Never state that tests passed unless they were actually executed.

------------------------------------------------------------------------

## 13. Git Safety

Before work:

``` bash
git status
git branch --show-current
```

After work:

``` bash
git diff
git status
```

Do not:

-   force push
-   reset user changes
-   discard unrelated modifications
-   rewrite history
-   merge branches automatically

unless explicitly instructed.

Keep implementation changes on the appropriate feature branch.

Recommended branch naming:

``` text
feature/product
feature/inventory-core
feature/inventory-receive
feature/inventory-fifo
feature/inventory-ui
fix/inventory-*
test/inventory-*
```

------------------------------------------------------------------------

## 14. Audit Mode

If the task says:

-   audit
-   inspect
-   analyze
-   review
-   scan
-   investigate

assume **read-only mode** unless the user explicitly asks for
modifications.

Audit output should include:

1.  what was inspected
2.  current architecture
3.  current implementation state
4.  conflicts
5.  risks
6.  missing pieces
7.  recommended next steps

Do not modify files during an audit.

------------------------------------------------------------------------

## 15. Design Mode

If the task is a design gate:

-   define data model
-   define business rules
-   define algorithm
-   define transaction boundary
-   define locking strategy
-   define invariants
-   define test cases

Do not implement until the design gate is approved when the phase
requires approval.

------------------------------------------------------------------------

## 16. Conflict Handling

If code conflicts with the locked design:

``` text
STOP
↓
Identify conflict
↓
Show affected files
↓
Explain consequences
↓
Offer possible resolutions
↓
Wait for project-owner decision when architectural
```

Never silently migrate the project to a different architecture.

------------------------------------------------------------------------

## 17. Output Requirements

After implementation, report:

``` text
### Summary
What changed

### Files
Created:
- ...

Modified:
- ...

### Business Logic
What behavior changed

### Tests
Commands actually executed
Results

### Notes
Known limitations/conflicts
```

Keep the report concise but complete.

------------------------------------------------------------------------

## 18. Explicit Prohibitions

Do not:

-   add quantity to Product
-   bypass InventoryService
-   implement FIFO in UI
-   directly mutate inventory from controllers
-   replace StockLot with StockMovement
-   replace StockMovement with StockLot
-   silently switch FIFO to FEFO
-   require expiry dates for all products
-   add serial-number tracking without approval
-   add ERP features without approval
-   introduce microservices without approval
-   install dependencies without approval
-   change database schema outside the active phase
-   perform unrelated refactors
-   claim tests passed without running them
-   overwrite user changes

------------------------------------------------------------------------

## 19. Definition of Done

A task is complete only when:

-   relevant project docs were read
-   current phase was respected
-   existing implementation was inspected
-   architecture was preserved
-   business invariants remain valid
-   transaction behavior is correct
-   relevant tests were run
-   diff was reviewed
-   no unrelated changes were introduced
-   changed files are reported
-   required documentation is updated

The project owner has final authority over requirements, architecture,
and merging.
