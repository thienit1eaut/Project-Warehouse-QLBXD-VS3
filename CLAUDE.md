# CLAUDE.md

## 1. Project Identity

This repository is the graduation project **Đồ án quản lý kho
(DATN2026-VS2)**.

The current project scope is inventory management for bicycle-related
products, including:

-   bicycles
-   spare parts
-   accessories
-   inner tubes / tires
-   chains
-   brakes
-   components

Some products may not have an expiry date. `expiry_date` may therefore
be nullable and exists as a future extension point. Do not force expiry
management, FEFO, serial-number tracking, ERP behavior, or microservice
architecture into the current graduation-project scope unless explicitly
requested.

The project owner is the final authority on business requirements and
architectural decisions.

------------------------------------------------------------------------

## 2. Source of Truth

Use this priority order when interpreting the project:

1.  Explicit instruction from the project owner in the current task
2.  `docs/ai/current-phase.md`
3.  `docs/ai/project-context.md`
4.  `docs/ai/coding-rules.md`
5.  `docs/architecture/*`
6.  `docs/database/*`
7.  `docs/business/*`
8.  Existing implementation and tests
9.  General framework conventions

If implementation conflicts with the documented architecture, **do not
silently redesign the system**.

Report:

-   the conflict
-   affected files
-   possible solutions
-   your recommended resolution

Then wait for the project owner when the conflict requires an
architectural/business decision.

------------------------------------------------------------------------

## 3. Phase Discipline

Always read:

-   `docs/ai/current-phase.md`
-   `docs/ai/project-context.md`
-   `docs/ai/coding-rules.md`

before making changes.

The current phase is controlled by the project owner.

Do not implement future-phase functionality merely because it appears
useful.

If the current phase is design/audit only:

-   do not modify production code
-   do not create migrations
-   do not refactor
-   do not install dependencies
-   do not "fix" unrelated issues

For implementation phases, change only what is necessary for that phase.

------------------------------------------------------------------------

## 4. Required First Step: Repository Audit

Before modifying an unfamiliar area:

1.  Inspect the repository structure.
2.  Read the relevant documentation.
3.  Inspect existing migrations/schema.
4.  Inspect models/entities.
5.  Inspect services/use cases.
6.  Inspect repositories/data-access code.
7.  Inspect controllers/routes.
8.  Inspect tests.
9.  Identify dependencies and integration points.
10. Determine what already works.

Do not assume that a file, class, migration, service, or table does not
exist until you have checked.

Do not recreate functionality that already exists.

------------------------------------------------------------------------

## 5. Locked Inventory Architecture

The inventory architecture is:

``` text
Product
   │
   └── Stock
         │
         └── StockLot

Warehouse
   │
   └── Stock

InventoryService
   ├── Receive
   ├── Issue
   └── Adjustment

StockMovement
   │
   └── StockAllocation
             │
             └── StockLot
```

### Product

`Product` represents master data.

It does **not** represent inventory quantity.

Do not add inventory quantity fields to `Product`.

Examples of product information:

-   SKU/code
-   name
-   category
-   unit
-   price
-   status
-   other product master attributes

Inventory quantity belongs to `Stock`.

### Warehouse

`Warehouse` represents a physical or logical warehouse/location.

### Stock

`Stock` represents the current total quantity of one product in one
warehouse.

Conceptually:

``` text
Stock = current quantity of Product X in Warehouse Y
```

### StockLot

`StockLot` represents quantity originating from one receiving/inbound
event.

A StockLot is not necessarily a manufacturing batch.

Important fields/concepts:

-   warehouse
-   product
-   quantity received
-   quantity remaining
-   received timestamp
-   optional `expiry_date`

Constraints:

``` text
quantity_received > 0

0 <= quantity_remaining <= quantity_received
```

### StockMovement

`StockMovement` is the inventory audit/history record.

Typical movement types:

-   `IN`
-   `OUT`
-   `ADJUSTMENT`

Do not replace StockMovement with StockLot.

The concepts are intentionally separate:

``` text
Stock        = how much exists now?
StockLot     = which receiving-origin quantities remain?
StockMovement = what inventory changes happened?
StockAllocation = which lots were consumed by an outbound movement?
```

### StockAllocation

`StockAllocation` records how an `OUT` movement consumes quantities from
StockLots.

For an outbound movement:

``` text
SUM(StockAllocation.quantity)
=
StockMovement.quantity
```

This enables traceability from outbound inventory back to receiving
lots.

------------------------------------------------------------------------

## 6. Core Inventory Invariant

For every `(warehouse_id, product_id)`:

``` text
Stock.quantity_on_hand
=
SUM(StockLot.quantity_remaining)
```

This invariant must remain true after successful inventory operations.

Never introduce a code path that updates Stock without preserving the
StockLot relationship where StockLots are part of the current phase.

------------------------------------------------------------------------

## 7. Inventory Mutation Boundary

All inventory mutations must pass through the inventory business layer:

``` text
Controller / UI
       ↓
InventoryService
       ↓
Repository / Persistence
       ↓
Database
```

Controllers must not contain inventory business logic.

UI must not:

-   calculate FIFO
-   select lots
-   update Stock directly
-   update StockLot directly
-   create StockMovement directly
-   bypass InventoryService

Do not create alternate inventory mutation paths unless explicitly
approved.

------------------------------------------------------------------------

## 8. Transaction Rules

Inventory-changing operations must be atomic.

At minimum, the following related changes must succeed or fail together:

### Receive

``` text
Stock
StockLot
StockMovement(IN)
```

### Issue

``` text
Stock
StockLot(s)
StockMovement(OUT)
StockAllocation(s)
```

### Adjustment

Changes to Stock, affected StockLots, and the corresponding
StockMovement must remain consistent.

If an operation fails, the database transaction must roll back the
entire inventory operation.

Do not leave partial inventory state.

------------------------------------------------------------------------

## 9. FIFO Rules

Current outbound inventory consumption uses FIFO.

Eligible lots:

-   same warehouse
-   same product
-   `quantity_remaining > 0`

Ordering:

``` text
received_at ASC
id ASC
```

The `id ASC` tie-breaker is required for deterministic behavior when
receiving timestamps are equal.

Example:

``` text
Lot 1: received 01/06, remaining 20
Lot 2: received 15/06, remaining 30
Lot 3: received 01/07, remaining 50

Issue 25:

Lot 1: 20 → 0
Lot 2: 30 → 25
Lot 3: 50 → 50

Stock:
100 → 75

Allocations:
Lot 1 = 20
Lot 2 = 5
```

Do not silently change FIFO to FEFO.

Expiry-based FEFO is a future extension and is not part of the current
default behavior.

------------------------------------------------------------------------

## 10. Concurrency

Inventory operations must account for concurrent requests.

Example:

``` text
Stock = 10

Transaction A wants to issue 7
Transaction B wants to issue 5
```

Both transactions must not independently read 10 and both commit
successfully.

Use the framework/database's appropriate transaction and row-locking
mechanisms.

Locking must protect:

-   the relevant Stock row
-   relevant StockLots
-   any related inventory state required by the implementation

The exact locking implementation must respect the project's existing
database/framework conventions.

------------------------------------------------------------------------

## 11. Adjustment Rules

Positive adjustment:

-   increase inventory
-   preserve the Stock/StockLot invariant
-   create an appropriate adjustment-origin lot when StockLots are used

Negative adjustment:

-   decrease inventory through a defined lot-consumption strategy
-   current recommended strategy is FIFO
-   never arbitrarily modify a random StockLot

An adjustment must also create an auditable `ADJUSTMENT` movement.

------------------------------------------------------------------------

## 12. Layer Responsibilities

### Controller

Responsible for:

-   request handling
-   authorization integration
-   input validation integration
-   calling application/service layer
-   formatting responses

Not responsible for:

-   FIFO
-   lot allocation
-   stock calculations
-   transaction orchestration
-   direct inventory mutation

### Service / Application Layer

Responsible for:

-   business rules
-   transaction boundary
-   inventory orchestration
-   FIFO allocation
-   invariant enforcement
-   domain/application validation

### Repository / Persistence Layer

Responsible for:

-   database queries
-   persistence
-   retrieving records
-   locking queries where appropriate

Do not move business rules into repositories merely to make controllers
shorter.

### UI

Responsible for:

-   displaying inventory data
-   collecting user input
-   showing validation/errors
-   submitting commands

UI must not implement authoritative inventory logic.

------------------------------------------------------------------------

## 13. Database Change Rules

Do not change database schema outside the current phase.

Before creating a migration:

1.  inspect existing migrations
2.  inspect current schema
3.  inspect related models
4.  inspect current phase
5.  verify the migration is actually required

Do not create duplicate tables, columns, indexes, or constraints.

Preserve existing naming conventions.

Prefer reversible migrations when practical.

------------------------------------------------------------------------

## 14. Dependencies

Do not install a new package/library/framework dependency without
explicit approval.

If a dependency appears necessary:

1.  explain why
2.  identify alternatives
3.  identify impact
4.  wait for approval when it changes the project's dependency surface

Do not solve a small problem by introducing a large dependency.

------------------------------------------------------------------------

## 15. Coding Rules

Follow existing project conventions for:

-   naming
-   namespaces
-   directories
-   formatting
-   validation
-   error handling
-   dependency injection
-   database access
-   tests
-   API response structure

Prefer small, focused changes.

Do not perform unrelated refactoring during a feature task.

Do not rewrite working code simply because another style is personally
preferred.

------------------------------------------------------------------------

## 16. Testing Requirements

After implementation:

1.  run relevant tests
2.  run broader tests when appropriate
3.  inspect failures
4.  fix regressions caused by the change
5.  report remaining failures honestly

Inventory tests should cover at least, where applicable:

-   receiving creates a StockLot
-   Stock increases correctly
-   receiving creates an IN movement
-   FIFO selects the oldest eligible lot
-   FIFO consumes multiple lots
-   insufficient stock is rejected
-   StockAllocation totals equal OUT quantity
-   Stock/StockLot invariant remains true
-   negative inventory is prevented
-   adjustment behavior
-   transaction rollback
-   deterministic ordering
-   concurrency-sensitive behavior where practical

Never claim tests passed unless they were actually run.

------------------------------------------------------------------------

## 17. Error Handling

Inventory errors should be explicit and meaningful.

Examples:

-   product not found
-   warehouse not found
-   insufficient stock
-   invalid quantity
-   invalid lot
-   invalid inventory state
-   concurrency/locking failure

Do not hide inventory integrity failures behind generic success
responses.

------------------------------------------------------------------------

## 18. Git Safety

Before significant changes:

``` text
inspect git status
inspect current branch
```

Keep changes focused.

After changes:

``` text
inspect git diff
run tests
review changed files
```

Do not reset, revert, force-push, delete branches, or discard user
changes unless explicitly instructed.

Never overwrite unrelated user work.

------------------------------------------------------------------------

## 19. AI Working Style

When asked to implement:

1.  State briefly what you found.
2.  Identify the files that need changing.
3.  Make the smallest coherent change.
4.  Run relevant tests.
5.  Review the diff.
6.  Report:
    -   files created
    -   files modified
    -   key logic changed
    -   tests run/results
    -   unresolved issues

When asked to audit:

-   do not modify files unless explicitly requested
-   distinguish confirmed facts from assumptions
-   cite exact file paths and relevant code locations

When requirements are ambiguous:

-   do not invent business rules
-   ask a focused question, or
-   if the ambiguity does not affect architecture, state the assumption
    explicitly

------------------------------------------------------------------------

## 20. Do Not Do These Things

Never:

-   put quantity on Product
-   bypass InventoryService
-   calculate FIFO in UI
-   update Stock directly from a controller
-   silently redesign the inventory architecture
-   replace StockLot with StockMovement
-   replace StockMovement with StockLot
-   implement FEFO instead of FIFO without approval
-   add expiry requirements to all products
-   introduce serial-number tracking without approval
-   introduce microservices without approval
-   install dependencies without approval
-   change schema outside the current phase
-   modify unrelated modules unnecessarily
-   claim tests passed without running them
-   erase or overwrite user work

------------------------------------------------------------------------

## 21. Completion Checklist

Before declaring a task complete:

-   [ ] Current phase was checked
-   [ ] Relevant docs were read
-   [ ] Existing implementation was inspected
-   [ ] Architecture was preserved
-   [ ] No unauthorized dependency was added
-   [ ] No unauthorized schema change was made
-   [ ] Inventory mutations remain inside InventoryService
-   [ ] Transactions are correct
-   [ ] Stock/StockLot invariant is preserved
-   [ ] FIFO rules are preserved where applicable
-   [ ] Tests were run
-   [ ] Git diff was reviewed
-   [ ] Changed files were reported
-   [ ] Documentation was updated if required

------------------------------------------------------------------------

## 22. Final Authority

This file provides project-level operating rules for Claude.

The project owner remains the final authority.

If a requirement, existing implementation, or AI suggestion conflicts
with the locked architecture, stop before making an architectural change
and surface the conflict clearly.
