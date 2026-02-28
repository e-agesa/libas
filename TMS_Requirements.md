# Tailor Management System (TMS)

## Detailed Wireframe Requirements Document

**Version:** 1.0
**Date:** February 2026
**Specialization:** Kanzus & Islamic Attire
**Status:** CONFIDENTIAL

---

## 1. Executive Summary

This document defines the detailed screen-by-screen wireframe requirements for the **Tailor Management System (TMS)**. The system is purpose-built for tailoring businesses specializing in Kanzus and Islamic attire, enabling a **measurement-first workflow** that links:

- **Clients** (billing entities)
- **Contacts** (individuals measured)
- **Measurement Profiles** (garment-specific dimensions)

...into a unified invoicing engine.

### Key Pain Points Resolved

- Eliminates paper-based measurement books
- Reduces cutting errors by explicitly linking measurements to invoices
- Supports multi-person billing where one client pays for multiple family members

---

## 2. User Roles & Permissions

| Role | Access Level | Key Capabilities |
|------|-------------|-----------------|
| **Admin** | Full Access | All features, user management, system settings, reports |
| **Manager** | Elevated | Client/contact management, invoicing, measurement management, reporting |
| **Tailor/Staff** | Standard | View clients, take measurements, view invoices assigned to them |
| **Secretary** | Invoice Focus | Create invoices, manage clients, print receipts, view measurements |

---

## 3. Global Navigation & Layout

The application uses a **sidebar navigation pattern** with a persistent top bar. All screens share a consistent layout framework.

| Component | Position | Contents |
|-----------|----------|----------|
| **Top Bar** | Fixed Top | App logo/name, search bar (global client search), notification bell, user avatar + dropdown (profile, settings, logout) |
| **Sidebar** | Fixed Left (collapsible) | Dashboard, Clients, Contacts, Measurements, Invoices, Fabrics/Materials, Reports, Settings |
| **Main Content** | Center | Dynamic area — renders the active screen |
| **Breadcrumb** | Below Top Bar | Navigation path showing current location (e.g., `Clients > Ali Hassan > Contacts > Musa`) |

---

## 4. Screen-by-Screen Wireframe Requirements

### 4.1 Dashboard (Home)

**Purpose:** At-a-glance operational summary for the business with quick-action shortcuts.

#### UI Elements

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Summary Cards | Stat Cards (4) | Yes | Today's Orders, Pending Invoices, Active Clients, Revenue (MTD) |
| Recent Orders | Table/List | Yes | Last 10 orders with client name, garment type, status, due date |
| Upcoming Deadlines | Calendar Widget | No | Orders due within next 7 days highlighted |
| Quick Actions | Button Group | Yes | New Client, New Invoice, Take Measurement |
| Revenue Chart | Bar/Line Chart | No | Monthly revenue trend (last 6 months) |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| + New Client | Click | Opens Client Creation modal/screen |
| + New Invoice | Click | Opens Invoice Creation wizard |
| + Take Measurement | Click | Opens Contact selector then Measurement form |
| View All Orders | Click | Navigates to full Orders/Invoices list |
| Order Row Click | Click on row | Navigates to Invoice Detail screen |

#### Design Notes

- Dashboard should auto-refresh every 60 seconds
- Cards should show percentage change from previous period
- Mobile: Stack cards vertically, collapse chart

---

### 4.2 Client List Screen

**Purpose:** Browse, search, and manage all clients (billing entities). A Client can have multiple Contacts under them.

#### UI Elements

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Search Bar | Text Input + Filter | No | Search by client name, phone, or email |
| Filter Dropdown | Select | No | Filter: All, Active, Inactive |
| Client Table | Data Table | Yes | Columns: Name, Phone, Email, Contacts Count, Total Orders, Last Visit, Actions |
| Pagination | Pagination Controls | Yes | 10/25/50 per page selector |
| + Add Client | Primary Button | Yes | Top-right positioned CTA button |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| + Add Client | Click | Opens Add Client modal with form fields |
| Table Row Click | Click | Navigates to Client Detail screen |
| Edit (action) | Icon click | Opens Edit Client modal pre-filled |
| Delete (action) | Icon click | Confirmation dialog, then soft-delete |
| Sort Column | Click header | Toggles ascending/descending sort |
| Export | Button click | Downloads client list as CSV/Excel |

#### Validation Rules

- Deletion requires confirmation modal
- Cannot delete client with unpaid invoices

#### Design Notes

- Highlight clients with pending orders in a subtle accent color

---

### 4.3 Add/Edit Client Modal

**Purpose:** Capture or update client billing information. This is the top-level entity that receives invoices.

#### UI Elements

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Full Name | Text Input | Yes | Client's full name (billing entity) |
| Phone Number | Phone Input | Yes | Primary contact number with country code |
| Alt. Phone | Phone Input | No | Secondary phone number |
| Email | Email Input | No | For sending digital receipts |
| Address | Textarea | No | Physical or delivery address |
| Notes | Textarea | No | General notes about this client |
| Client Type | Select | No | Individual, Family, Corporate, Institution |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| Save | Click | Validates all required fields, saves record, closes modal, refreshes list |
| Cancel | Click | Discards changes, closes modal |
| Add First Contact | Toggle/Checkbox | After save, immediately open Add Contact form for this client |

#### Validation Rules

- Full Name is required and must be at least 2 characters
- Phone must be valid format (supports +254 Kenyan format)
- Email must be valid format if provided
- Duplicate phone number should show warning (not block)

---

### 4.4 Client Detail Screen

**Purpose:** Full profile of a single client showing their contacts, measurement history, and invoice history. This is the central hub for the measurement-first workflow.

#### UI Elements

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Client Header | Info Card | Yes | Name, phone, email, client type, join date, total spent |
| Contacts Tab | Tab Panel | Yes | List of all contacts (family members / individuals) under this client |
| Invoices Tab | Tab Panel | Yes | All invoices for this client with status filters |
| Activity Log | Tab Panel | No | Timeline of all actions (measurements taken, invoices created, payments) |
| + Add Contact | Button | Yes | Adds a new contact/person under this client |
| + New Invoice | Button | Yes | Starts invoice creation pre-linked to this client |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| + Add Contact | Click | Opens Add Contact modal scoped to this client |
| Contact Card Click | Click | Navigates to Contact Detail with measurement profiles |
| + New Invoice | Click | Opens Invoice Wizard with client pre-selected |
| Invoice Row Click | Click | Opens Invoice Detail |
| Edit Client | Header action | Opens Edit Client modal |
| Print Statement | Button | Generates PDF of all client invoices |

#### Design Notes

- Contacts displayed as cards showing: name, garment count, last measurement date
- Badge on contact card showing number of saved measurement profiles
- Invoice tab shows quick filters: All, Paid, Unpaid, Overdue

---

### 4.5 Add/Edit Contact Modal

**Purpose:** Register an individual person who will have garments made. A contact belongs to a client and holds their own measurement profiles.

#### UI Elements

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Full Name | Text Input | Yes | The person being measured (e.g., the son, brother) |
| Relationship | Select | No | Self, Son, Daughter, Brother, Sister, Wife, Husband, Employee, Other |
| Phone | Phone Input | No | Contact's own phone (optional) |
| Gender | Radio/Select | No | Male, Female (helps filter garment types) |
| Age Group | Select | No | Child, Teen, Adult, Senior (affects measurement ranges) |
| Notes | Textarea | No | Special notes (e.g., prefers loose fit, sensitive skin) |
| Photo | Image Upload | No | Optional profile photo for identification |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| Save | Click | Creates contact under parent client, closes modal |
| Save & Add Measurement | Click | Saves contact then immediately opens Measurement form |
| Cancel | Click | Discards and closes |

#### Validation Rules

- Full Name is required
- Contact name must be unique within the same client

---

### 4.6 Contact Detail — Measurement Vault

**Purpose:** The core of the system. Shows all saved measurement profiles for a specific contact, organized by garment type. This is the "Digital Measuring Tape".

#### UI Elements

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Contact Header | Info Card | Yes | Name, parent client, relationship, photo, age group |
| Measurement Cards | Card Grid | Yes | One card per saved measurement, grouped by garment type (Kanzu, Shirt, Trouser, Vest) |
| Card Content | Within each card | Yes | Garment type icon, measurement date, label (e.g. "2024 Kanzu"), key dimensions preview |
| + Add Measurement | FAB / Button | Yes | Floating action button to add new measurement |
| History Timeline | Timeline | No | Shows measurement changes over time for growth tracking |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| + Add Measurement | Click | Opens Measurement Form with garment type selector |
| Measurement Card Click | Click | Expands to full measurement detail view |
| Edit Measurement | Card action icon | Opens Measurement Form pre-filled for editing |
| Delete Measurement | Card action icon | Confirmation dialog, then removes measurement |
| Duplicate Measurement | Card action icon | Copies measurement as new entry with today's date (useful for minor adjustments) |
| Compare | Select 2 cards | Side-by-side comparison of two measurement profiles |

#### Design Notes

- Group measurements by garment type with collapsible sections
- Show most recent measurement prominently at top
- Color-code garment types: **Kanzu=Green**, **Shirt=Blue**, **Trouser=Brown**, **Vest=Purple**
- Each card shows a mini visual silhouette indicating which body parts are measured

---

### 4.7 Measurement Entry Form

**Purpose:** Dynamic form that changes fields based on the selected garment type. This captures the actual body dimensions.

#### Common Fields

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Garment Type | Select/Tabs | Yes | Kanzu, Shirt, Trouser, Vest/Full — changes visible fields dynamically |
| Measurement Label | Text Input | Yes | User-defined label (e.g., "2026 Eid Kanzu", "School Shirt") |
| Date Taken | Date Picker | Yes | Defaults to today |
| Unit Toggle | Toggle (cm/inches) | Yes | Switch between cm and inches with auto-conversion |
| Notes | Textarea | No | Fit preferences, special instructions |
| Measured By | Select | No | Which tailor/staff took the measurement |

#### Kanzu Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| Length | Number (cm/in) | Yes | Full garment length |
| Chest | Number | Yes | Chest circumference |
| Shoulder | Number | Yes | Shoulder width |
| Sleeve | Number | Yes | Sleeve length |
| Neck | Number | Yes | Neck circumference |
| Cross-Back | Number | No | Back width between shoulder blades |
| Pocket Depth | Number | No | Kanzu pocket depth preference |

#### Shirt Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| Neck | Number | Yes | Neck circumference |
| Chest | Number | Yes | Chest circumference |
| Waist | Number | Yes | Waist measurement |
| Sleeve Length | Number | Yes | Sleeve length |
| Cuff Width | Number | No | Cuff opening width |
| Shirt Length | Number | Yes | Total shirt length |
| Sleeve Type | Select | Yes | Cuff / Open |

#### Trouser Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| Waist | Number | Yes | Waist circumference |
| Hips | Number | Yes | Hip circumference |
| Thigh | Number | No | Thigh circumference |
| Knee | Number | No | Knee circumference |
| Outseam | Number | Yes | Full outer leg length |
| Ankle/Bottom | Number | Yes | Bottom opening width |

#### Vest/Full Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| Full Body Length | Number | Yes | Neck to ankle |
| Waist Circ. | Number | Yes | Waist circumference |
| Shoulder to Waist | Number | Yes | Shoulder to waist distance |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| Select Garment Type | Tab/Select change | Shows/hides relevant field groups dynamically |
| Save Measurement | Click | Validates required fields, saves to contact's vault |
| Save & Start Invoice | Click | Saves measurement then opens Invoice Wizard with this contact+measurement pre-selected |
| Cancel | Click | Discards and returns to Contact Detail |
| Unit Toggle | Toggle | Converts all entered values between cm and inches in real-time |

#### Validation Rules

- All number fields must be positive values
- Reasonable range validation: e.g., Neck 10–60cm, Chest 50–200cm, Length 50–200cm
- At least all "Required" fields for the selected garment type must be filled
- Warn if measurement values differ significantly from previous measurement of same type (>15% change)

#### Design Notes

- Show a human body silhouette diagram highlighting where each measurement is taken
- Use visual step-by-step flow: select type → enter values → review → save
- Auto-suggest label based on garment type + year (e.g., "2026 Kanzu")

---

### 4.8 Invoice Creation Wizard (Multi-Step)

**Purpose:** The core invoicing engine. A step-by-step wizard that combines Client selection, Contact picking, Measurement linking, and Fabric assignment into line items. Supports multi-person billing.

#### Step Indicator

Progress Bar: `1. Client → 2. Line Items → 3. Review → 4. Finalize`

#### Step 1: Client Selection

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Client Search | Autocomplete Input | Yes | Search and select existing client (or create new) |
| Client Preview | Info Card | Yes | Shows selected client's name, phone, outstanding balance |

#### Step 2: Line Items

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| + Add Line Item | Button | Yes | Adds a new garment line item row |
| Contact Dropdown | Select per line | Yes | Shows all contacts under selected client |
| Measurement Dropdown | Select per line | Yes | Shows saved measurements for selected contact (filtered by garment type) |
| Fabric/Material | Select per line | Yes | Dropdown of available fabrics (with price per unit) |
| Quantity | Number Input | Yes | Number of this garment (default: 1) |
| Craftsmanship Fee | Number Input | Yes | Tailoring/labor charge for this item |
| Fabric Cost | Auto-calculated | Yes | Based on fabric selection + quantity |
| Line Total | Auto-calculated | Yes | Fabric Cost + Craftsmanship Fee |
| Remove Line | Icon Button | Yes | Removes this line item |

#### Step 3: Review

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Line Items Summary | Table | Yes | All items with contact, garment, measurement, fabric, costs |
| Subtotal | Display | Yes | Sum of all line totals |
| Discount | Number Input | No | Flat amount or percentage discount |
| Tax | Auto/Manual | No | Tax calculation if applicable |
| Grand Total | Display (bold) | Yes | Final amount after discount and tax |

#### Step 4: Finalize

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Payment Method | Select | Yes | Cash, M-Pesa, Bank Transfer, Credit |
| Amount Paid | Number Input | No | Partial or full payment amount |
| Balance Due | Auto-calculated | Yes | Grand Total minus Amount Paid |
| Due Date | Date Picker | No | Expected completion/delivery date |
| Notes | Textarea | No | Special instructions for the workshop |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| Next Step | Click | Validates current step and advances to next |
| Previous Step | Click | Goes back without losing data |
| + Add Line Item | Click | Inserts new blank line item row |
| Contact Change | Dropdown change | Reloads measurement options for that contact |
| Measurement Change | Dropdown change | Auto-fills garment type, shows measurement summary tooltip |
| Fabric Change | Dropdown change | Recalculates fabric cost |
| Save as Draft | Click | Saves invoice in Draft status |
| Finalize & Print | Click | Saves invoice as Issued, generates printable receipt |
| Finalize & Send | Click | Saves and sends digital receipt via SMS/email |

#### Validation Rules

- At least one line item is required to proceed past Step 2
- Each line item must have: Contact, Measurement, Fabric, and Craftsmanship Fee
- Craftsmanship Fee must be > 0
- Amount Paid cannot exceed Grand Total
- Client must be selected before adding line items

#### Design Notes

- Each line item visually shows: Contact name → Measurement label → Fabric → Cost breakdown
- Measurement dropdown shows preview: "Musa's 2024 Kanzu (Chest:42, Length:58)"
- Support adding multiple garments for the same contact in separate lines
- Show running total as items are added
- Highlight if a contact has no saved measurements (prompt to add one)

---

### 4.9 Invoice Detail / Receipt Screen

**Purpose:** Read-only view of a finalized invoice. Can be printed or shared as a receipt.

#### UI Elements

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Invoice Header | Display | Yes | Invoice #, date, status badge (Draft/Issued/Paid/Overdue) |
| Client Info | Display | Yes | Client name, phone, email |
| Line Items Table | Table | Yes | Contact name, garment type, measurement ref, fabric, qty, craftsmanship, line total |
| Totals Section | Display | Yes | Subtotal, discount, tax, grand total, amount paid, balance due |
| Payment History | Table/List | No | Log of payments made (date, amount, method) |
| Workshop Notes | Display | No | Special instructions for the tailor |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| Print Receipt | Click | Opens print dialog with formatted receipt layout |
| Download PDF | Click | Generates and downloads PDF receipt |
| Send via SMS | Click | Sends receipt summary to client phone |
| Send via Email | Click | Sends formatted receipt to client email |
| Record Payment | Click | Opens payment recording modal (amount, method, date) |
| Edit Invoice | Click (only if Draft) | Returns to Invoice Wizard in edit mode |
| Void Invoice | Click | Marks invoice as voided with reason (requires admin) |

#### Design Notes

- Receipt layout should include: business logo/name, invoice #, all line items with measurement references
- Measurement details printed alongside each line item so workshop knows exact dimensions
- Status badge color-coded: **Draft=Gray**, **Issued=Blue**, **Paid=Green**, **Overdue=Red**, **Voided=Dark Gray**

---

### 4.10 Fabric & Materials Management

**Purpose:** Manage the inventory of available fabrics/materials that can be linked to invoice line items.

#### UI Elements

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Fabric Table | Data Table | Yes | Columns: Name, Type, Color, Price/Unit, Stock Qty, Status |
| Search | Text Input | No | Filter by name or type |
| + Add Fabric | Button | Yes | Opens Add Fabric modal |
| Category Filter | Select | No | Linen, Cotton, Wool, Polyester, Silk, Custom |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| + Add Fabric | Click | Opens modal: Name, Type, Color, Price per unit, Stock quantity, Supplier, Notes |
| Edit | Row action | Opens edit modal |
| Deactivate | Row action | Soft-disables fabric from invoice selection |
| Adjust Stock | Row action | Quick stock update (+/- quantity) |

#### Validation Rules

- Name is required and unique
- Price must be > 0
- Stock quantity cannot be negative

#### Design Notes

- Show color swatch circle next to fabric name
- Low stock indicator when quantity < threshold (configurable)
- Most-used fabrics should appear first in invoice dropdowns

---

### 4.11 Reports Dashboard

**Purpose:** Business intelligence and operational reports for management.

#### UI Elements

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Date Range Picker | Date Range | Yes | Filter all reports by date range |
| Revenue Report | Chart + Table | Yes | Revenue by period with breakdown by garment type |
| Client Report | Table | Yes | Top clients by revenue, order count |
| Garment Report | Chart | Yes | Orders by garment type (Kanzu vs Shirt vs Trouser vs Vest) |
| Outstanding Invoices | Table | Yes | All unpaid/overdue invoices with aging |
| Fabric Usage | Chart + Table | No | Most used fabrics, stock consumption rate |
| Tailor Performance | Table | No | Orders completed per tailor/staff member |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| Export PDF | Click | Generates full report as PDF |
| Export Excel | Click | Downloads report data as Excel spreadsheet |
| Date Range Change | Selection | Refreshes all report widgets |
| Drill Down | Click chart segment | Opens detailed view for that data point |

---

### 4.12 Settings Screen

**Purpose:** System configuration and business preferences.

#### UI Elements

| Element | Type | Required | Description |
|---------|------|----------|-------------|
| Business Profile | Form Section | Yes | Business name, logo, phone, address (appears on receipts) |
| Default Unit | Toggle | Yes | Default measurement unit: cm or inches |
| Currency | Select | Yes | KES, USD, etc. |
| Tax Settings | Form | No | Enable/disable tax, tax rate percentage |
| Invoice Numbering | Form | No | Prefix and starting number (e.g., INV-0001) |
| User Management | Table + Forms | Yes | Add/edit/deactivate staff users with role assignment |
| Receipt Template | Preview + Edit | No | Customize receipt layout, footer text |
| Low Stock Threshold | Number Input | No | Alert threshold for fabric inventory |
| SMS/Email Config | Form | No | SMS gateway settings, email SMTP or provider |
| Backup & Export | Button Group | No | Full data export, database backup |

#### Actions & Behaviors

| Action/Button | Trigger | Expected Behavior |
|---------------|---------|-------------------|
| Save Settings | Click | Persists all changed settings |
| Upload Logo | File Upload | Uploads and displays business logo |
| Test SMS | Click | Sends test SMS to verify configuration |
| Test Email | Click | Sends test email to verify configuration |
| Backup Now | Click | Triggers immediate data backup |

#### Validation Rules

- Business name is required
- Tax rate must be 0–100%
- Logo must be image format (PNG, JPG) under 2MB

---

## 5. Data Model Summary

The following outlines the key entities and their relationships driving the system:

### Entity Relationship Diagram

```
Client (1) ──────< (N) Contact
  │                      │
  │                      │
  ├──< (N) Invoice       ├──< (N) Measurement
  │         │            │           │
  │         │            │           │
  │         ├──< (N) Invoice Line Item >── Fabric
  │         │         │
  │         │         └── References: Contact + Measurement + Fabric
  │         │
  │         └──< (N) Payment
  │
  └── User (system access)
```

### Entities

| Entity | Key Fields | Relationships |
|--------|-----------|---------------|
| **Client** | id, name, phone, email, address, type, notes, status | Has many Contacts, Has many Invoices |
| **Contact** | id, client_id, name, relationship, gender, age_group, phone, notes, photo | Belongs to Client, Has many Measurements |
| **Measurement** | id, contact_id, garment_type, label, date, unit, values (JSON), measured_by, notes | Belongs to Contact, Referenced by Invoice Line Items |
| **Invoice** | id, client_id, invoice_number, date, status, subtotal, discount, tax, total, paid, balance, due_date, notes | Belongs to Client, Has many Line Items, Has many Payments |
| **Invoice Line Item** | id, invoice_id, contact_id, measurement_id, fabric_id, quantity, craftsmanship_fee, fabric_cost, line_total | Belongs to Invoice, References Contact + Measurement + Fabric |
| **Fabric** | id, name, type, color, price_per_unit, stock_qty, supplier, status | Referenced by Invoice Line Items |
| **Payment** | id, invoice_id, amount, method, date, reference | Belongs to Invoice |
| **User** | id, name, email, role, status | System access entity |

---

## 6. Primary Workflow Summary

The **measurement-first workflow** follows this critical path:

| Step | Screen | Action | Output |
|------|--------|--------|--------|
| 1 | Client List / Detail | Identify or create the billing Client | Client record |
| 2 | Client Detail | Add Contact(s) under the Client | Contact records linked to Client |
| 3 | Measurement Form | Take measurements for each Contact by garment type | Measurement profiles in the Vault |
| 4 | Invoice Wizard — Step 1 | Select the Client | Client linked to invoice |
| 5 | Invoice Wizard — Step 2 | Add line items: pick Contact → Measurement → Fabric | Line items with full traceability |
| 6 | Invoice Wizard — Step 3 | Review totals, apply discounts | Validated invoice |
| 7 | Invoice Wizard — Step 4 | Record payment, set due date, finalize | Issued invoice + receipt |
| 8 | Invoice Detail | Print/send receipt to client | Physical/digital receipt with measurement references |

---

## 7. Non-Functional Requirements

| Requirement | Specification |
|------------|---------------|
| **Responsive Design** | Must work on desktop (1920px), tablet (768px), and mobile (375px) |
| **Performance** | Page load < 2 seconds, search results < 500ms |
| **Offline Capability** | Measurement entry should work offline and sync when connected |
| **Data Backup** | Automatic daily backups with manual backup option |
| **Print Support** | Receipts and reports must render correctly on thermal and A4 printers |
| **Browser Support** | Chrome, Firefox, Safari, Edge (latest 2 versions) |
| **Language** | English primary, with framework for Swahili localization |
| **Security** | Role-based access, encrypted passwords, session management |
| **Accessibility** | WCAG 2.1 AA compliance for core workflows |

---

## 8. Measurement Attributes Reference

Complete measurement fields by garment type for implementation reference:

| Garment Type | Required Measurements | Optional Measurements |
|-------------|----------------------|----------------------|
| **Kanzu** | Length, Chest, Shoulder, Sleeve, Neck | Cross-Back, Pocket Depth |
| **Shirt** | Neck, Chest, Waist, Sleeve Length, Shirt Length, Sleeve Type | Cuff Width |
| **Trouser** | Waist, Hips, Outseam, Ankle/Bottom | Thigh, Knee |
| **Vest/Full** | Full Body Length, Waist Circumference, Shoulder to Waist | — |
