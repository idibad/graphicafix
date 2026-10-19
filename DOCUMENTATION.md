# Graphicafix — Complete Technical Documentation & File Hierarchy

**Graphicafix** is a comprehensive, full-stack PHP web application that integrates a **Digital Agency Platform**, a **Customer Relationship Management (CRM) System**, and a fully featured **Learning Management System (LMS)** into a single unified portal.

---

## 1. System Overview & Architecture

### **Technology Stack**
* **Backend Language:** PHP (using Procedural and OOP MySQLi for database interactions).
* **Database:** MySQL / MariaDB (Schema stored in `database/graficafix.sql`).
* **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5 (CSS/JS bundled in `assets/`).
* **Dependency Management:** Composer (`composer.json` / `composer.lock`).
* **PDF Generation Engine:** `mpdf/mpdf` (used for dynamically generating invoices, quotations, letters, and certificates).
* **Environment & Configuration:** `vlucas/phpdotenv` (loads environment variables from `.env`).
* **Payment Gateways:** Integrated with **SafePay** and **JazzCash** for course enrollments and billing.

### **Core Architectural Patterns**
1. **Centralized Configuration (`core/config.php`):**
   * Manages secure MySQLi database connections with automatic error reporting.
   * Configures PHP session management with a built-in **15-minute inactivity timeout** and redirection to `/admin/login.php?expired=1`.
   * Sets system timezone to `Asia/Karachi` (`+05:00`).
   * Loads credentials and API keys dynamically from the root `.env` file.
2. **Modular Directory Structure:**
   * **`/admin`**: Enterprise dashboard for agency operations, LMS administration, CRM, invoicing, and staff management.
   * **`/portal`**: Dedicated LMS portal for students (course learning, quizzes, assignments) and teachers (course management, grading, lectures).
   * **`/pages`, `/courses`, `/blogs`, `/projects`**: Public-facing website sections and marketing funnels.
   * **`/api` & `/core`**: AJAX endpoints, webhook handlers (JazzCash/SafePay), CSRF token validation, and password security utilities.

---

## 2. Complete File Hierarchy

Below is the exhaustive file and directory hierarchy of the Graphicafix platform:

```text
c:\xampp2\htdocs\graphicafix\
├── .env                              # Environment configuration (DB credentials, payment API keys)
├── .gitignore                        # Git ignore rules
├── .htaccess                         # Apache server routing and rewrite rules
├── 403.php                           # HTTP 403 Forbidden error page
├── 404.php                           # HTTP 404 Not Found error page
├── chat.php                          # Live chat and support widget interface
├── composer.json                     # Composer dependencies (mPDF, phpdotenv)
├── composer.lock                     # Locked dependency versions
├── download_form.php                 # Secure file download handler
├── index.php                         # Public website homepage / landing page
├── out.html                          # Static output/cache preview HTML
├── replace_admin_emojis.js           # Utility script for emoji sanitization/replacement in admin
├── replace_admin_emojis_part2.js     # Secondary emoji processing script
├── robots.txt                        # Search engine crawler instructions
├── sitemap.xml                       # XML sitemap for SEO indexing
├── visitor_counter.php               # Visitor tracking and analytics script
│
├── admin/                            # ADMIN & AGENCY MANAGEMENT DASHBOARD (85 Files)
│   ├── index.php                     # Admin dashboard overview & analytics
│   ├── login.php                     # Admin authentication login page
│   ├── login_history.php             # Audit logs of user login attempts
│   ├── logout.php                    # Session termination and cleanup
│   ├── password.php                  # Password reset and recovery utility
│   ├── profile.php                   # Admin profile view
│   ├── profile_edit.php              # Admin profile customization
│   ├── dashboard_header.php          # Shared admin navigation and top bar
│   ├── dashboard_footer.php          # Shared admin footer and scripts
│   ├── main.php                      # Main admin layout wrapper
│   │
│   ├── [LMS & Course Management]
│   ├── manage_courses.php            # Comprehensive course catalog administration
│   ├── admin_lectures.php            # Lecture content and video management
│   ├── admin_teacher_docs.php        # Teacher documentation and verification
│   ├── teacher_courses.php           # Teacher-course assignment management
│   ├── teacher_lectures.php          # Teacher lecture supervision
│   ├── teacher_assignments.php       # Teacher assignment monitoring
│   ├── teacher_documents.php         # Teacher resource document management
│   ├── teacher_notices.php           # Announcements directed to teachers
│   ├── student_courses.php           # Student enrollment and progress tracking
│   ├── student_lectures.php          # Student lecture viewing analytics
│   ├── student_assignments.php       # Student assignment submissions & grading
│   ├── student_certificates.php      # Course completion certificate generation
│   ├── student_live.php              # Live class / webinar scheduling for students
│   ├── student_queries.php           # Student Q&A and support tickets
│   ├── student_my_queries.php        # Individual query tracking
│   ├── attendance.php                # Student and teacher attendance logs
│   ├── lecture_action.php            # CRUD controller for lectures
│   ├── assignment_action.php         # CRUD controller for assignments
│   ├── document_action.php           # CRUD controller for educational documents
│   │
│   ├── [Agency Projects & Services]
│   ├── manage_projects.php           # Portfolio and client project administration
│   ├── project_add.php               # New project creation form
│   ├── project_details.php           # Detailed project view and milestone tracking
│   ├── edit_project.php              # Project modification interface
│   ├── delete_project.php            # Project deletion handler
│   ├── project_requests.php          # Incoming client project inquiries/requests
│   ├── manage_services.php           # Agency service catalog administration
│   ├── service_add.php               # Service creation form
│   ├── service_details.php           # Service detailed breakdown
│   ├── edit_service.php              # Service editing interface
│   ├── delete_service.php            # Service deletion handler
│   │
│   ├── [CRM, Leads & Clients]
│   ├── manage_leads.php              # Sales pipeline and lead tracking
│   ├── manage_clients.php            # Client relationship management database
│   ├── clients.php                   # Client directory overview
│   ├── contacts.php                  # Contact form submissions log
│   │
│   ├── [Billing, Invoicing & Quotations]
│   ├── manage_invoices.php           # Invoice billing overview
│   ├── create_invoice.php            # Invoice builder and line-item editor
│   ├── edit_invoice.php              # Invoice modification tool
│   ├── invoice_details.php           # Invoice printable view & status tracking
│   ├── invoices.php                  # Invoice list summary
│   ├── generate_invoice_pdf.php      # mPDF invoice generator script
│   ├── create_quotation.php          # Quotation builder for prospective clients
│   ├── edit_quotation.php            # Quotation modification tool
│   ├── quotation_details.php         # Quotation preview and approval tracking
│   ├── generate_quotation_pdf.php    # mPDF quotation generator script
│   │
│   ├── [Letters, DPR & Correspondence]
│   ├── correspondence.php            # Official correspondence and letter archive
│   ├── generate_letter_pdf.php       # mPDF official letter generator script
│   ├── dpr.php                       # Daily Progress Reports (DPR) management
│   ├── dpr_action.php                # DPR submission and review controller
│   │
│   ├── [Task & Team Management]
│   ├── tasks.php                     # Internal team task board
│   ├── create_task.php               # Task assignment and deadline builder
│   ├── task_details.php              # Task discussion and progress tracking
│   ├── task_action.php               # Task CRUD controller
│   ├── update_task_status.php        # Quick AJAX status update endpoint
│   ├── manage_users.php              # Admin and staff role/account management
│   ├── user_action.php               # User account CRUD controller
│   ├── manage_applications.php       # Job applications / career submissions
│   │
│   ├── [Content, Communications & Media]
│   ├── manage_blogs.php              # Blog article management
│   ├── create_blog.php               # Blog post authoring tool
│   ├── edit_blog.php                 # Blog post editor
│   ├── manage_notices.php            # Notice board administration
│   ├── notice_action.php             # Notice CRUD controller
│   ├── manage_notifications.php      # System notification dispatcher
│   ├── notifications.php             # Notification feed view
│   ├── messages.php                  # Internal messaging center
│   ├── fetch_messages.php            # AJAX endpoint for polling messages
│   ├── send_message.php              # Message sending handler
│   ├── newsletter.php                # Newsletter subscriber list & broadcast
│   ├── reviews.php                   # Testimonials and client review management
│   ├── review_toggle.php             # Review visibility toggle handler
│   ├── manage_resources.php          # Downloadable resource library management
│   ├── entertainment.php             # Internal staff entertainment/media module
│   ├── visitor_widget.php            # Analytics widget configuration
│   ├── export.php                    # Data export utility (CSV/Excel)
│   ├── remove_gallery_image.php      # Media gallery cleanup handler
│   ├── images/                       # Admin-specific UI images
│   └── uploads/                      # Admin file attachment storage
│
├── api/                              # AJAX ENDPOINTS & UTILITIES (5 Files)
│   ├── check_discount.php            # Coupon code and discount validation endpoint
│   ├── get_csrf_token.php            # CSRF token generator for secure forms
│   ├── hash_password.php             # Single password bcrypt/argon2 hashing utility
│   ├── hash_passwords.php            # Batch password hashing script for migrations
│   └── subscribe_handler.php         # Newsletter AJAX subscription handler
│
├── assets/                           # STATIC FRONTEND ASSETS
│   ├── css/                          # Stylesheets
│   │   ├── all.css                   # FontAwesome / icon font styles
│   │   ├── bootstrap.css             # Bootstrap 5 framework stylesheet
│   │   ├── dashboard.css             # Dashboard-specific layout styles
│   │   └── style.css                 # Main custom application stylesheet
│   ├── js/                           # JavaScript files
│   │   ├── bootstrap.bundle.js       # Bootstrap 5 bundle (includes Popper)
│   │   ├── bootstrap.js              # Core Bootstrap JavaScript
│   │   ├── imagemin.js               # Image compression utility script
│   │   ├── scripts.js                # Custom frontend interactivity and AJAX logic
│   │   ├── package.json              # Frontend package metadata
│   │   └── Node.js                   # Node environment marker / config
│   ├── fonts/                        # Local typography and icon font files
│   └── images/                       # Global UI graphics, logos, and placeholders
│
├── blogs/                            # PUBLIC BLOG MODULE (2 Files)
│   ├── blogs.php                     # Public blog post listing / archive page
│   └── blog_post.php                 # Individual blog post reader page
│
├── core/                             # CORE SYSTEM & CONFIGURATION (4 Files)
│   ├── config.php                    # DB connection, session rules, timezone, .env loader
│   ├── functions.php                 # Global helper functions and sanitization tools
│   ├── jazzcash_callback.php         # JazzCash payment gateway IPN / webhook handler
│   └── safepay_callback.php          # SafePay payment gateway IPN / webhook handler
│
├── courses/                          # PUBLIC COURSE CATALOG (5 Files)
│   ├── courses.php                   # LMS course catalog listing page
│   ├── course_detail.php             # Comprehensive course curriculum & enrollment page
│   ├── course_progress.php           # Public/student course progress tracker
│   ├── course_thank_you.php          # Post-enrollment payment confirmation page
│   └── manage_courses.php            # Frontend course management helper
│
├── database/                         # DATABASE STORAGE (1 File)
│   └── graficafix.sql                # Complete MySQL database schema and initial dump (6MB)
│
├── images/                           # PUBLIC IMAGE REPOSITORY
│   └── [Media Files]                 # Uploaded course banners, project thumbnails, logos
│
├── letters/                          # GENERATED & ARCHIVED PDF DOCUMENTS (7 Files)
│   ├── ALgo_platform_description_ID4.pdf
│   ├── Blog_For_Student_for_Courses_ID6.pdf
│   ├── DOC_1.pdf
│   ├── DOC_2.pdf
│   ├── DOC_3.pdf
│   ├── Revised_subscripton_Payment_ID3.pdf
│   └── student_policy_ID5.pdf
│
├── pages/                            # PUBLIC STATIC & MARKETING PAGES (7 Files)
│   ├── about.php                     # About Us agency profile page
│   ├── career.php                    # Job openings and career application page
│   ├── contact.php                   # Contact form and location details page
│   ├── portfolio.php                 # Showcase of agency case studies and work
│   ├── privacy-policy.php            # Legal privacy policy documentation
│   ├── services.php                  # Detailed breakdown of digital agency services
│   └── terms.php                     # Terms and conditions of service
│
├── portal/                           # STUDENT & TEACHER LMS PORTAL (22 Files)
│   ├── index.php                     # Portal dashboard (role-based view for student/teacher)
│   ├── login.php                     # Portal login authentication
│   ├── logout.php                    # Portal session cleanup
│   ├── profile.php                   # User profile management
│   ├── dashboard_header.php          # Portal navigation bar
│   ├── dashboard_footer.php          # Portal footer and script inclusions
│   ├── ajax_mark_complete.php        # AJAX endpoint to mark lectures/lessons as complete
│   │
│   ├── [Student Features]
│   ├── student_courses.php           # Enrolled courses dashboard
│   ├── course_player.php             # Interactive video lecture and lesson player
│   ├── student_assignments.php       # Assignment view and submission upload form
│   ├── student_certificates.php      # Downloadable PDF certificates of completion
│   ├── student_live.php              # Access to scheduled live webinars/classes
│   ├── student_queries.php           # Ask questions to instructors / view answers
│   ├── take_quiz.php                 # Interactive MCQ quiz examination interface
│   │
│   ├── [Teacher Features]
│   ├── teacher_courses.php           # Teacher's assigned courses overview
│   ├── teacher_lectures.php          # Lecture upload and content management
│   ├── teacher_assignments.php       # Create assignments and grade student submissions
│   ├── teacher_documents.php         # Upload course study materials and notes
│   ├── teacher_resources.php         # Manage supplementary external resources
│   ├── teacher_quizzes.php           # Quiz builder and student result evaluation
│   ├── teacher_announcements.php     # Post course-wide announcements to students
│   └── teacher_queries.php           # Respond to student academic queries
│
├── projects/                         # PROJECT INQUIRIES & MANAGEMENT (5 Files)
│   ├── project_add.php               # Frontend project submission / intake form
│   ├── project_details.php           # Public/client project status breakdown
│   ├── edit_project.php              # Project update interface
│   ├── project_request_popup.php     # Modal popup dialog for requesting quotes/projects
│   └── project_request_submission.php # Processing script for project inquiries
│
├── quotations/                       # GENERATED QUOTATION ARCHIVE (19 Files)
│   ├── QUO-2026-001.pdf to QUO-2026-019.pdf # PDF records of agency client quotations
│
├── src/                              # PSR-4 AUTOLOAD DIRECTORY
│   └── [Personal/Graphicafix]        # Namespace root for custom classes
│
├── templates/                        # SHARED UI TEMPLATES (4 Files)
│   ├── default.php                   # Default page layout wrapper
│   ├── header.php                    # Main website header and navigation menu
│   ├── footer.php                    # Main website footer, links, and copyright
│   └── dashboard_header.php          # Alternative dashboard navigation header
│
├── uploads/                          # USER & SYSTEM FILE UPLOADS
│   └── [User Uploads]                # Student assignment submissions, teacher documents, etc.
│
└── vendor/                           # COMPOSER MANAGED LIBRARIES
    ├── mpdf/                         # mPDF PDF generation library
    ├── vlucas/                       # PHP Dotenv library
    └── [autoload.php / composer]     # PSR-4 autoloader files
```

---

## 3. Module Breakdown & Key Workflows

### 3.1 Learning Management System (LMS) Workflow
1. **Course Creation & Management:**
   * Admins create courses via `/admin/manage_courses.php` and assign teachers via `/admin/teacher_courses.php`.
   * Teachers log into `/portal/index.php`, navigate to `/portal/teacher_lectures.php` to upload video lectures, and attach study materials via `/portal/teacher_documents.php`.
2. **Student Enrollment & Learning:**
   * Students browse `/courses/courses.php`, view details on `/courses/course_detail.php`, and enroll via **SafePay** or **JazzCash** payment gateways.
   * Webhook handlers (`/core/safepay_callback.php` or `/core/jazzcash_callback.php`) automatically verify transactions and activate enrollment.
   * Students access `/portal/course_player.php` to watch lessons. As they finish lessons, `/portal/ajax_mark_complete.php` records progress asynchronously.
3. **Assessment & Certification:**
   * Teachers build quizzes (`/portal/teacher_quizzes.php`) and assignments (`/portal/teacher_assignments.php`).
   * Students complete tests via `/portal/take_quiz.php`. Upon 100% course completion and passing grades, `/portal/student_certificates.php` uses `mpdf` to generate a verifiable PDF certificate.

---

### 3.2 Digital Agency & CRM Workflow
1. **Lead Generation & Client Intake:**
   * Prospective clients submit inquiries via `/pages/contact.php`, `/projects/project_request_popup.php`, or `/download_form.php`.
   * Leads are logged into `/admin/manage_leads.php` and converted to active clients in `/admin/manage_clients.php`.
2. **Quotations & Invoicing:**
   * Staff build customized estimates using `/admin/create_quotation.php`. The system compiles line items and generates professional PDF proposals (`/admin/generate_quotation_pdf.php` saved in `/quotations/`).
   * Once approved, invoices are created via `/admin/create_invoice.php` and sent to clients (`/admin/generate_invoice_pdf.php`).
3. **Project & Task Execution:**
   * Agency projects are tracked in `/admin/manage_projects.php`. Daily Progress Reports (DPR) are logged by team members via `/admin/dpr.php`.
   * Internal deliverables are managed on the team task board (`/admin/tasks.php` and `/admin/update_task_status.php`).

---

### 3.3 Security, Authentication & Session Management
* **Session Lifecycle:** Managed in `/core/config.php`. Sessions include an inactivity timer (`$timeout_duration = 1800` seconds / 15 minutes). If exceeded, the session is destroyed and redirected to login.
* **Password Security:** Users passwords are encrypted using modern hashing algorithms handled via `/api/hash_password.php`.
* **CSRF Protection:** Forms utilize tokens generated from `/api/get_csrf_token.php` to prevent cross-site request forgery attacks.
* **Role-Based Access Control (RBAC):** Strict segregation between Admin (`/admin`), Staff/Teachers (`/portal`), and Students (`/portal`), with session validation checks on every protected page.
