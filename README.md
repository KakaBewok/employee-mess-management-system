# Employee & Mess Room Management System

## System Documentation

---

## Screenshots

![Home Page](https://res.cloudinary.com/dk16ng09n/image/upload/v1767431047/personal/itci/root_sf9eqc.png)
![Login](https://res.cloudinary.com/dk16ng09n/image/upload/v1767431146/personal/itci/login_zxamqv.png)
![Dashboard](https://res.cloudinary.com/dk16ng09n/image/upload/v1767431145/personal/itci/dashboard_vzo5kb.png)
![Table](https://res.cloudinary.com/dk16ng09n/image/upload/v1767431176/personal/itci/table_hdkv2u.png)
![Chart](https://res.cloudinary.com/dk16ng09n/image/upload/v1767431198/personal/itci/cart_oxk0sp.png)
![Pivot Grid](https://res.cloudinary.com/dk16ng09n/image/upload/v1767431175/personal/itci/pivot_k17u8p.png)
![Allocation Page](https://res.cloudinary.com/dk16ng09n/image/upload/v1767431197/personal/itci/allocate_lhncmo.png)
![Guest](https://res.cloudinary.com/dk16ng09n/image/upload/v1767431199/personal/itci/guest_gcgb9k.png)

## 1. SYSTEM OVERVIEW

The Employee and Mess Room Management System is a web-based application built with Laravel that manages employee records, room allocations, and guest accommodations for a company facility. The system provides a centralized platform for administrators to track employee information, manage room inventory, and allocate rooms to both employees and guests efficiently.

### Key Features

-   **Employee Management**: Complete CRUD operations for employee records with status tracking
-   **Room Management**: Manage room inventory with capacity controls and occupancy monitoring
-   **Allocation System**: Smart room allocation with capacity validation and conflict prevention
-   **Guest Management**: Track guest visits and room assignments
-   **Visual Analytics**: Charts and pivot tables for data visualization using DevExtreme

---

## 2. TECHNOLOGY STACK

| Component         | Technology     | Version |
| ----------------- | -------------- | ------- |
| Backend Framework | Laravel        | 12.x    |
| Authentication    | Laravel Breeze | Latest  |
| Frontend          | Laravel Blade  | -       |
| UI Components     | DevExtreme     | 23.2.3  |
| Database          | MySQL          | 8.0+    |
| CSS Framework     | Tailwind CSS   | 3.x     |

---

## 3. SYSTEM MODULES

### 3.1 Authentication Module

-   **Implementation**: Laravel Breeze
-   **Access Level**: Single Admin role
-   **Features**:
    -   Secure login/logout/regiter
    -   Password reset functionality
    -   Session management
    -   CSRF protection

### 3.2 Employee Management Module

**Features**:

-   Add, edit, and delete employee records
-   Employee status management (Active/Inactive)
-   Department assignment
-   Employee code validation (unique identifier)
-   Name validation (A-Z characters only)
-   Status distribution visualization

**Business Rules**:

-   Total active employees: 60
-   Total inactive employees: 12
-   Employee codes must be unique
-   Names must contain only alphabetic characters

### 3.3 Room Management Module

**Features**:

-   CRUD operations for rooms
-   Capacity management (1 or 2 persons)
-   Automatic status updates (Empty/Occupied)
-   Real-time occupancy tracking
-   Capacity vs. occupancy pivot analysis

**Business Rules**:

-   20 rooms with capacity of 1 person
-   25 rooms with capacity of 2 persons
-   Status automatically updates based on allocations

### 3.4 Guest Management Module

**Features**:

-   Register guest information
-   Track visit dates
-   Link guests to room allocations
-   Average weekly guest tracking

**Business Rules**:

-   Average guest attendance: 4 persons per week
-   Guests can be allocated to available rooms

### 3.5 Room Allocation Module

**Features**:

-   Allocate rooms to employees or guests
-   Prevent over-capacity allocations
-   Release room allocations
-   Track allocation history
-   Real-time availability checking

**Business Rules**:

-   One allocation per employee/guest at a time
-   Room capacity must not be exceeded
-   Either employee OR guest (not both) per allocation
-   Automatic room status updates

---

## 4. DATABASE STRUCTURE

### 4.1 Entity Relationship Diagram (ERD)

```
users (Laravel Breeze)
├── id (PK)
├── name
├── email (unique)
├── password
└── timestamps

employees
├── id (PK)
├── employee_code (unique)
├── name
├── department (enum)
├── status (enum)
└── timestamps

rooms
├── id (PK)
├── room_code (unique)
├── capacity (1 or 2)
├── status (enum)
└── timestamps

guests
├── id (PK)
├── name
├── visit_date
└── timestamps

room_allocations
├── id (PK)
├── room_id (FK → rooms.id)
├── employee_id (FK → employees.id, nullable)
├── guest_id (FK → guests.id, nullable)
├── allocated_at
├── released_at (nullable)
└── timestamps
```

### 4.2 Table Relationships

**One-to-Many Relationships**:

-   `rooms` → `room_allocations` (one room can have many allocations over time)
-   `employees` → `room_allocations` (one employee can have many allocations over time)
-   `guests` → `room_allocations` (one guest can have many allocations over time)

**Active Allocation Constraints**:

-   Only one active allocation (where `released_at IS NULL`) per employee
-   Only one active allocation per guest
-   Room capacity determines maximum concurrent active allocations

### 4.3 Database Constraints

1. **Unique Constraints**:

    - `employees.employee_code`
    - `rooms.room_code`
    - `users.email`

2. **Foreign Key Constraints**:

    - `room_allocations.room_id` references `rooms.id` (CASCADE DELETE)
    - `room_allocations.employee_id` references `employees.id` (CASCADE DELETE)
    - `room_allocations.guest_id` references `guests.id` (CASCADE DELETE)

3. **Check Constraints** (Application Level):
    - Either `employee_id` OR `guest_id` must be filled (not both)
    - Room capacity must not be exceeded by active allocations

---

## 5. ARCHITECTURE & DESIGN PATTERNS

### 5.1 MVC Pattern

```
┌─────────────┐      ┌──────────────┐      ┌───────────┐
│   Routes    │ ───> │ Controllers  │ ───> │  Models   │
└─────────────┘      └──────────────┘      └───────────┘
                            │                     │
                            ▼                     ▼
                     ┌──────────────┐      ┌───────────┐
                     │   Services   │      │ Database  │
                     └──────────────┘      └───────────┘
                            │
                            ▼
                     ┌──────────────┐
                     │    Views     │
                     └──────────────┘
```

### 5.2 Service Layer Pattern

**Purpose**: Encapsulate complex business logic

**RoomAllocationService**:

-   Validates room capacity
-   Checks for existing active allocations
-   Handles transaction management
-   Updates room status automatically
-   Prevents allocation conflicts

### 5.3 Request Validation

Form Request classes handle validation:

-   `EmployeeRequest`: Validates employee data
-   `RoomRequest`: Validates room data
-   `AllocationRequest`: Validates allocation rules

---

## 6. KEY BUSINESS LOGIC

### 6.1 Room Allocation Logic

// Allocation Process Flow:

1. Validate input (room exists, employee/guest exists)
2. Check room capacity availability
3. Verify no active allocation exists for employee/guest
4. Create allocation record
5. Update room status based on occupancy
6. Commit transaction or rollback on error

### 6.2 Capacity Management

// Room Capacity Calculation:

Current Occupancy = COUNT(active_allocations WHERE released_at IS NULL)
Available Slots = Room Capacity - Current Occupancy
Is Available = Available Slots > 0

### 6.3 Status Updates

**Room Status**:

-   `empty`: No active allocations
-   `occupied`: One or more active allocations

**Employee Status**:

-   `active`: Currently employed
-   `inactive`: No longer employed

---

## 7. DEVEXTREME COMPONENTS

### 7.1 DataGrid Usage

**Employee Management**:

-   Inline editing with popup forms
-   Column filtering and searching
-   Sorting and pagination
-   Data export functionality

**Room Management**:

-   Real-time occupancy display
-   Capacity visualization
-   Status indicators

**Allocation Management**:

-   Active allocation listing
-   Release actions
-   Date formatting

### 7.2 Chart Component

**Employee Status Chart**:

-   Bar chart visualization
-   Active vs Inactive comparison
-   Interactive tooltips

### 7.3 PivotGrid Component

**Room Capacity Analysis**:

-   Multi-dimensional data view
-   Capacity grouping
-   Occupancy summaries
-   Grand totals

---

## 8. SECURITY FEATURES

1. **Authentication**:

    - Laravel Breeze secure authentication
    - Password hashing (bcrypt)
    - Session-based authentication

2. **Authorization**:

    - All routes protected by `auth` middleware
    - CSRF token validation on all forms

3. **Input Validation**:

    - Server-side validation via Form Requests
    - XSS prevention through Blade escaping
    - SQL injection prevention via Eloquent ORM

4. **Database Security**:
    - Foreign key constraints
    - Transaction management for data integrity

---

## 9. INSTALLATION & SETUP

### Step 1: Clone and Install

```bash
composer create-project laravel/laravel employee-mess-system
cd employee-mess-system
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install && npm run dev
```

### Step 2: Database Configuration

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=employee_mess_db
DB_USERNAME=root
DB_PASSWORD=
```

### Step 3: Run Migrations & Seed

```bash
php artisan migrate
php artisan db:seed
```

### Step 4: Access System

```
running with:
npm run start

URL: http://localhost:8000
Admin Email: admin@example.com
Password: password
```

---

## 10. ASSUMPTIONS & LIMITATIONS

### Assumptions

1. **Single Admin Role**: System designed for one administrative role without complex permission structures
2. **Room Types**: Only two capacity types (1 and 2 persons) are supported
3. **Allocation Rule**: One person (employee or guest) can only occupy one room at a time
4. **Guest Tracking**: Guests are tracked by visit date; historical data is maintained
5. **Network Environment**: System requires stable internet connection for DevExtreme CDN

### Limitations

1. **Scalability**: Current design optimized for small to medium organizations
2. **Reporting**: Limited to built-in charts and pivot tables
3. **Multi-tenancy**: Not designed for multiple organizations
4. **Mobile App**: Web-only interface, no native mobile application
5. **Real-time Updates**: Manual refresh required for grid updates
6. **File Storage**: No document upload functionality for employees
7. **Notification System**: No automated alerts for allocation conflicts
8. **Audit Trail**: Limited historical tracking of changes

### Future Enhancements

-   Role-based access control (multiple admin levels)
-   Email notifications for allocations
-   Advanced reporting and analytics
-   Mobile-responsive dashboard improvements
-   REST API for third-party integrations
-   Automated room assignment algorithms
-   Calendar view for allocations
-   Employee photo uploads

---

## 11. TESTING GUIDELINES

### Manual Testing Checklist

**Employee Module**:

-   [ ] Create employee with valid data
-   [ ] Attempt duplicate employee code
-   [ ] Update employee information
-   [ ] Delete employee
-   [ ] Validate name with numbers (should fail)
-   [ ] View status chart

**Room Module**:

-   [ ] Create room with capacity 1
-   [ ] Create room with capacity 2
-   [ ] Attempt duplicate room code
-   [ ] View occupancy in pivot grid

**Allocation Module**:

-   [ ] Allocate employee to room
-   [ ] Allocate guest to room
-   [ ] Attempt over-capacity allocation
-   [ ] Attempt duplicate allocation
-   [ ] Release allocation
-   [ ] Verify room status updates

---

## CONCLUSION

This Employee and Mess Room Management System provides a comprehensive solution for managing facility resources efficiently. Built with Laravel best practices and modern UI components, the system offers reliability, security, and ease of use for administrative staff.
