<?php
/**
 * price_calculator.php
 * Fully Responsive Sales Price Calculator & Scope Estimator
 * Graphicafix Sales & Business Development Team
 */

require_once __DIR__ . '/../core/config.php';

// Authentication check
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Fetch user data
$username = $_SESSION['username'] ?? '';
$user_id  = $_SESSION['user_id'] ?? 0;
$role     = strtolower($_SESSION['role'] ?? 'user');

if (!in_array($role, ['super_admin', 'superadmin', 'admin', 'pm', 'manager', 'sales'])) {
    header("Location: index.php?error=unauthorized");
    exit;
}

// Fetch clients list safely for sales rep selection
$clients_list = [];
if (isset($conn) && $conn instanceof mysqli) {
    $c_res = @$conn->query("SELECT id, client_name, company, email, phone FROM clients ORDER BY client_name ASC");
    if ($c_res) {
        while ($c = $c_res->fetch_assoc()) {
            $clients_list[] = $c;
        }
    }
}

include 'dashboard_header.php';
?>

<style>
/* ==========================================================================
   Fully Responsive & Modern Design System (Graphicafix 2026)
   ========================================================================== */

:root {
    --calc-primary: #024442;
    --calc-primary-hover: #012e2c;
    --calc-primary-light: #036865;
    --calc-primary-surface: #f0fbf9;
    --calc-accent: #b8f35a;
    --calc-accent-hover: #9fe237;
    --calc-accent-surface: #f4fde6;
    --calc-accent-glow: rgba(184, 243, 90, 0.35);
    --calc-dark: #0f172a;
    --calc-slate-800: #1e293b;
    --calc-slate-700: #334155;
    --calc-slate-600: #475569;
    --calc-slate-500: #64748b;
    --calc-slate-400: #94a3b8;
    --calc-slate-300: #cbd5e1;
    --calc-slate-200: #e2e8f0;
    --calc-slate-100: #f1f5f9;
    --calc-slate-50: #f8fafc;
    
    --calc-radius-sm: 8px;
    --calc-radius-md: 12px;
    --calc-radius-lg: 16px;
    --calc-radius-xl: 20px;
    
    --calc-shadow-card: 0 2px 8px rgba(15, 23, 42, 0.04);
    --calc-shadow-hover: 0 10px 24px -4px rgba(2, 68, 66, 0.12);
    --calc-shadow-sidebar: 0 14px 35px -8px rgba(2, 68, 66, 0.2);
}

/* Layout Bounds & Sticky Viewport Support */
html, body {
    max-width: 100% !important;
}

.height-100 {
    width: 100% !important;
    max-width: 100% !important;
    padding: 14px 18px !important;
    box-sizing: border-box !important;
    overflow: visible !important;
}

.calc-container {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 auto;
    padding-bottom: 90px;
    box-sizing: border-box;
    overflow: visible !important;
}
.calc-hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(184, 243, 90, 0.15);
    border: 1px solid rgba(184, 243, 90, 0.35);
    color: var(--calc-accent);
    padding: 3px 10px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    margin-bottom: 8px;
}
.calc-hero-tag-dot {
    width: 6px;
    height: 6px;
    background: var(--calc-accent);
    border-radius: 50%;
    box-shadow: 0 0 8px var(--calc-accent);
    animation: heroPulse 1.6s infinite ease-in-out;
}
@keyframes heroPulse {
    0%, 100% { transform: scale(0.9); opacity: 0.8; }
    50% { transform: scale(1.3); opacity: 1; }
}

.calc-hero-title {
    font-size: clamp(1.25rem, 2vw, 1.7rem);
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 6px 0;
    letter-spacing: -0.4px;
    display: flex;
    align-items: center;
    gap: 10px;
    line-height: 1.25;
}
.calc-hero-subtitle {
    font-size: 0.86rem;
    color: #94b8b6;
    margin: 0;
    line-height: 1.45;
}

.calc-hero-actions {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 14px;
    border-radius: var(--calc-radius-sm);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
    white-space: nowrap;
}
.hero-btn-accent {
    background: var(--calc-accent);
    color: #0c1a17;
    box-shadow: 0 4px 12px var(--calc-accent-glow);
}
.hero-btn-accent:hover {
    background: var(--calc-accent-hover);
    color: #000;
    transform: translateY(-1px);
}
.hero-btn-glass {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.hero-btn-glass:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}
.hero-btn-danger-glass {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.3);
}
.hero-btn-danger-glass:hover {
    background: rgba(239, 68, 68, 0.25);
    color: #fff;
}

/* 2. Quick Packages Shelf */
.presets-shelf {
    background: #ffffff;
    border: 1px solid var(--calc-slate-200);
    border-radius: var(--calc-radius-md);
    padding: 10px 14px;
    margin-bottom: 16px;
    box-shadow: var(--calc-shadow-card);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    width: 100%;
    box-sizing: border-box;
}
.presets-shelf-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--calc-slate-600);
    flex-shrink: 0;
}
.presets-shelf-label i {
    color: #eab308;
}
.presets-track {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
    min-width: 0;
}
.preset-pill {
    background: var(--calc-slate-100);
    border: 1px solid var(--calc-slate-200);
    color: var(--calc-slate-700);
    padding: 4px 11px;
    border-radius: 50px;
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.preset-pill:hover {
    background: var(--calc-primary-surface);
    border-color: var(--calc-primary);
    color: var(--calc-primary);
}
.preset-pill.active {
    background: var(--calc-primary);
    border-color: var(--calc-primary);
    color: #ffffff;
}

/* 3. Client & Project Setup Strip */
.client-meta-card {
    background: #ffffff;
    border: 1px solid var(--calc-slate-200);
    border-radius: var(--calc-radius-md);
    padding: 14px 18px;
    margin-bottom: 18px;
    box-shadow: var(--calc-shadow-card);
    width: 100%;
    box-sizing: border-box;
}
.client-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr));
    gap: 12px;
    width: 100%;
    box-sizing: border-box;
}
.meta-field-box {
    position: relative;
    width: 100%;
    min-width: 0;
}
.meta-field-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--calc-slate-500);
    margin-bottom: 5px;
}
.meta-field-label i {
    color: var(--calc-primary);
}
.meta-field-input, .meta-field-select {
    width: 100%;
    box-sizing: border-box;
    padding: 7px 11px;
    border-radius: var(--calc-radius-sm);
    border: 1.5px solid var(--calc-slate-200);
    font-size: 0.84rem;
    font-weight: 600;
    color: var(--calc-dark);
    background: #ffffff;
    outline: none;
    transition: all 0.15s ease;
}
.meta-field-input:focus, .meta-field-select:focus {
    border-color: var(--calc-primary);
    box-shadow: 0 0 0 3px rgba(2, 68, 66, 0.08);
}

/* 4. Main Responsive Workspace Grid */
.calc-workspace {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 340px;
    gap: 20px;
    align-items: start;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Seamless Breakpoints for Dashboard Sidebar Context */
@media (max-width: 1260px) {
    .calc-workspace {
        grid-template-columns: minmax(0, 1fr) 310px;
        gap: 16px;
    }
}
@media (max-width: 1080px) {
    .calc-workspace {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .calc-cockpit-sidebar {
        position: static !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}

.calc-catalog-pane {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

/* 5. Search & Sub-Navigation Toolbar */
.toolbar-capsule {
    background: #ffffff;
    border: 1px solid var(--calc-slate-200);
    border-radius: var(--calc-radius-md);
    padding: 10px 14px;
    margin-bottom: 18px;
    box-shadow: var(--calc-shadow-card);
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    box-sizing: border-box;
}
.toolbar-top-row {
    display: flex;
    gap: 10px;
    align-items: center;
    width: 100%;
    flex-wrap: wrap;
}
.search-input-wrap {
    position: relative;
    flex: 1 1 240px;
    min-width: 0;
}
.search-input-wrap i.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--calc-slate-400);
    font-size: 0.88rem;
}
.search-input-wrap input {
    width: 100%;
    box-sizing: border-box;
    padding: 8px 34px 8px 36px;
    border-radius: var(--calc-radius-sm);
    border: 1.5px solid var(--calc-slate-200);
    font-size: 0.85rem;
    outline: none;
    background: var(--calc-slate-50);
    transition: all 0.15s ease;
}
.search-input-wrap input:focus {
    background: #ffffff;
    border-color: var(--calc-primary);
}
.search-clear-btn {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--calc-slate-200);
    border: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    color: var(--calc-slate-600);
    cursor: pointer;
    font-size: 0.7rem;
}

.btn-toggle-all-sections {
    background: var(--calc-slate-100);
    border: 1.5px solid var(--calc-slate-200);
    color: var(--calc-slate-700);
    padding: 7px 13px;
    border-radius: var(--calc-radius-sm);
    font-size: 0.78rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    transition: all 0.15s ease;
}
.btn-toggle-all-sections:hover {
    background: var(--calc-primary-surface);
    color: var(--calc-primary);
    border-color: var(--calc-primary);
}

.category-pills-row {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 2px;
    width: 100%;
    box-sizing: border-box;
    scrollbar-width: thin;
}
.cat-pill-btn {
    background: var(--calc-slate-100);
    border: 1px solid var(--calc-slate-200);
    color: var(--calc-slate-700);
    padding: 5px 11px;
    border-radius: var(--calc-radius-sm);
    font-size: 0.76rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
}
.cat-pill-btn:hover {
    background: #ffffff;
    border-color: var(--calc-primary-light);
    color: var(--calc-primary);
}
.cat-pill-btn.active {
    background: var(--calc-primary);
    border-color: var(--calc-primary);
    color: #ffffff;
}
.tab-btn-custom {
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.tab-btn-custom:hover {
    color: var(--calc-primary);
}
.tab-btn-custom.active {
    color: var(--calc-primary);
    border-bottom-color: var(--calc-primary);
    font-weight: 700;
}
.badge.has-selection, .cat-pill-counter.has-selection {
    background: #024442 !important;
    color: #ffffff !important;
}
.tab-btn-custom.active .badge.has-selection {
    background: #b8f35a !important;
    color: #0c1a17 !important;
}

/* 6. Highly Functional & Interactive Collapsible Section Cards */
.calc-section-card {
    background: #ffffff;
    border: 1.5px solid var(--calc-slate-200);
    border-radius: var(--calc-radius-lg);
    margin-bottom: 16px;
    box-shadow: var(--calc-shadow-card);
    transition: border-color 0.2s, box-shadow 0.2s;
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
}
.calc-section-card.has-active-items {
    border-color: rgba(2, 68, 66, 0.35);
    border-left: 4px solid var(--calc-primary);
}

.calc-section-header {
    padding: 14px 18px;
    background: #ffffff;
    border-bottom: 1px solid transparent;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s, border-color 0.15s;
    width: 100%;
    box-sizing: border-box;
    gap: 12px;
    flex-wrap: wrap; /* Guarantees zero width overflow on smaller screens */
}
.calc-section-card:not(.collapsed) .calc-section-header {
    border-bottom-color: var(--calc-slate-200);
    background: var(--calc-slate-50);
}
.calc-section-header:hover {
    background: var(--calc-slate-100);
}

.section-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 220px;
    flex: 1 1 auto;
}
.section-icon-badge {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    background: var(--calc-primary-surface);
    color: var(--calc-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
}
.section-titles {
    min-width: 0;
    flex: 1;
}
.section-titles h3 {
    font-size: 1rem;
    font-weight: 800;
    color: var(--calc-dark);
    margin: 0;
    letter-spacing: -0.2px;
    line-height: 1.3;
}
.section-titles p {
    font-size: 0.77rem;
    color: var(--calc-slate-500);
    margin: 2px 0 0 0;
    line-height: 1.35;
}

.section-header-right {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
    flex: 0 1 auto;
}

/* Functional Item Chips displayed right inside header when collapsed */
.section-chips-preview {
    display: none;
    flex-wrap: wrap;
    gap: 5px;
    max-width: 480px;
}
.calc-section-card.collapsed .section-chips-preview.has-chips {
    display: flex;
}
.section-preview-chip {
    background: var(--calc-primary-surface);
    border: 1px solid rgba(2, 68, 66, 0.25);
    color: var(--calc-primary);
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chip-remove-btn {
    border: none;
    background: transparent;
    color: var(--calc-slate-500);
    font-size: 0.75rem;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    display: flex;
    align-items: center;
}
.chip-remove-btn:hover {
    color: #ef4444;
}

/* Subtotal Summary Badge in Header */
.section-collapsed-summary {
    display: none;
    font-size: 0.75rem;
    font-weight: 800;
    color: #1e3a8a;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    padding: 3px 9px;
    border-radius: 6px;
    white-space: nowrap;
}
.calc-section-card.collapsed .section-collapsed-summary.has-data {
    display: inline-block;
}

.section-selected-pill {
    background: var(--calc-accent-surface);
    color: #27530e;
    border: 1px solid #c4f37a;
    padding: 3px 9px;
    border-radius: 50px;
    font-size: 0.73rem;
    font-weight: 800;
    display: none;
    white-space: nowrap;
}

/* Explicit Actionable Toggle Button */
.section-toggle-btn {
    background: var(--calc-slate-100);
    border: 1px solid var(--calc-slate-200);
    color: var(--calc-slate-700);
    padding: 4px 9px;
    border-radius: 6px;
    font-size: 0.73rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.calc-section-header:hover .section-toggle-btn {
    background: var(--calc-primary-surface);
    color: var(--calc-primary);
    border-color: var(--calc-primary-light);
}
.section-chevron-icon {
    font-size: 0.75rem;
    transition: transform 0.25s ease;
}
.calc-section-card:not(.collapsed) .section-chevron-icon {
    transform: rotate(180deg);
}

.calc-section-body {
    padding: 16px 18px;
    box-sizing: border-box;
    width: 100%;
}
.calc-section-card.collapsed .calc-section-body {
    display: none !important;
}

.advisory-callout {
    background: #fffdf5;
    border: 1px solid #fef3c7;
    border-left: 4px solid #f59e0b;
    border-radius: var(--calc-radius-sm);
    padding: 10px 14px;
    margin-bottom: 14px;
    font-size: 0.79rem;
    color: #92400e;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    line-height: 1.45;
}
.advisory-callout i {
    font-size: 0.95rem;
    color: #f59e0b;
    margin-top: 1px;
    flex-shrink: 0;
}

/* 7. Foundation Tier Base Cards */
.foundation-shelf-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr));
    gap: 12px;
    width: 100%;
    box-sizing: border-box;
}
.foundation-tier-card {
    border: 1.5px solid var(--calc-slate-200);
    border-radius: var(--calc-radius-md);
    padding: 14px 15px;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-width: 0;
    box-sizing: border-box;
}
.foundation-tier-card:hover {
    border-color: var(--calc-primary-light);
    transform: translateY(-2px);
    box-shadow: var(--calc-shadow-hover);
}
.foundation-tier-card.selected {
    border-color: var(--calc-primary);
    background: var(--calc-primary-surface);
    box-shadow: 0 0 0 1.5px var(--calc-primary), 0 8px 18px -4px rgba(2, 68, 66, 0.15);
}

.tier-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}
.tier-icon-wrap {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: var(--calc-slate-100);
    color: var(--calc-slate-700);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}
.foundation-tier-card.selected .tier-icon-wrap {
    background: var(--calc-primary);
    color: var(--calc-accent);
}
.tier-radio-indicator {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid var(--calc-slate-300);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.foundation-tier-card.selected .tier-radio-indicator {
    border-color: var(--calc-primary);
    background: var(--calc-primary);
}
.foundation-tier-card.selected .tier-radio-indicator::after {
    content: '';
    width: 7px;
    height: 7px;
    background: var(--calc-accent);
    border-radius: 50%;
}

.tier-badge {
    display: inline-block;
    font-size: 0.67rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 2px 7px;
    border-radius: 4px;
    background: var(--calc-slate-100);
    color: var(--calc-slate-600);
    margin-bottom: 5px;
}
.foundation-tier-card.selected .tier-badge {
    background: var(--calc-accent);
    color: #0c1a17;
}

.tier-title {
    font-size: 0.94rem;
    font-weight: 800;
    color: var(--calc-dark);
    margin: 0 0 4px 0;
    line-height: 1.35;
}
.tier-desc {
    font-size: 0.77rem;
    color: var(--calc-slate-600);
    margin-bottom: 12px;
    line-height: 1.4;
    flex-grow: 1;
}

.tier-bottom-bar {
    border-top: 1px dashed var(--calc-slate-200);
    padding-top: 9px;
    margin-top: 6px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.tier-price-capsule {
    font-size: 0.88rem;
    font-weight: 900;
    color: var(--calc-primary);
    font-feature-settings: "tnum";
}
.tier-timeline-tag {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--calc-slate-500);
    display: flex;
    align-items: center;
    gap: 4px;
}

/* 8. Add-on Module Unit Cards */
.modules-shelf-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr));
    gap: 12px;
    width: 100%;
    box-sizing: border-box;
}
.module-unit-card {
    border: 1.5px solid var(--calc-slate-200);
    border-radius: var(--calc-radius-md);
    padding: 13px 14px;
    background: #ffffff;
    transition: all 0.18s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    cursor: pointer;
    min-width: 0;
    box-sizing: border-box;
}
.module-unit-card:hover {
    border-color: #cbd5e1;
    background: #fafcfe;
    transform: translateY(-1px);
    box-shadow: var(--calc-shadow-hover);
}
.module-unit-card.selected {
    border-color: var(--calc-primary);
    background: var(--calc-primary-surface);
    box-shadow: 0 3px 10px rgba(2, 68, 66, 0.08);
}

.mod-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 8px;
}
.mod-card-details {
    flex: 1;
    min-width: 0;
}
.mod-unit-title {
    font-size: 0.89rem;
    font-weight: 800;
    color: var(--calc-dark);
    margin: 0 0 3px 0;
    line-height: 1.35;
}
.mod-unit-desc {
    font-size: 0.76rem;
    color: var(--calc-slate-500);
    margin: 0;
    line-height: 1.35;
}

/* Modern iOS Toggle Switch */
.mod-toggle-switch {
    position: relative;
    display: inline-block;
    width: 36px;
    height: 20px;
    flex-shrink: 0;
    margin-top: 1px;
}
.mod-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.switch-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: var(--calc-slate-300);
    transition: 0.2s ease;
    border-radius: 34px;
}
.switch-slider::before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.2s ease;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.25);
}
.mod-toggle-switch input:checked + .switch-slider {
    background-color: var(--calc-primary);
}
.mod-toggle-switch input:checked + .switch-slider::before {
    transform: translateX(16px);
    background-color: var(--calc-accent);
}

.mod-card-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px dashed var(--calc-slate-200);
    padding-top: 8px;
    margin-top: 6px;
    flex-wrap: wrap;
    gap: 6px;
}
.mod-price-pill {
    font-size: 0.85rem;
    font-weight: 900;
    color: var(--calc-primary);
    font-feature-settings: "tnum";
}
.mod-unit-note {
    font-size: 0.67rem;
    font-weight: 600;
    color: var(--calc-slate-400);
}

.mod-stepper {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1.5px solid var(--calc-slate-200);
    border-radius: 50px;
    padding: 2px 4px;
    gap: 3px;
}
.stepper-btn {
    width: 21px;
    height: 21px;
    border-radius: 50%;
    background: var(--calc-slate-100);
    border: none;
    color: var(--calc-dark);
    font-weight: 900;
    font-size: 0.75rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stepper-btn:hover {
    background: var(--calc-primary);
    color: #ffffff;
}
.stepper-val {
    width: 24px;
    text-align: center;
    border: none;
    background: transparent;
    font-weight: 800;
    font-size: 0.82rem;
    color: var(--calc-dark);
    outline: none;
}
.stepper-unit {
    font-size: 0.69rem;
    font-weight: 700;
    color: var(--calc-slate-500);
    margin-right: 3px;
}

/* 9. Responsive Sticky Pricing Cockpit (Right Column) */
.calc-cockpit-sidebar {
    position: -webkit-sticky;
    position: sticky;
    top: 85px;
    z-index: 25;
    align-self: start;
    width: 100%;
    box-sizing: border-box;
}
.cockpit-panel {
    background: #ffffff;
    border: 1.5px solid var(--calc-primary);
    border-radius: var(--calc-radius-lg);
    box-shadow: var(--calc-shadow-sidebar);
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
}

.cockpit-header {
    background: linear-gradient(145deg, #024442 0%, #012827 100%);
    color: #ffffff;
    padding: 18px 20px 15px 20px;
    position: relative;
    box-sizing: border-box;
}
.cockpit-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.cockpit-tag {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 50px;
}
.cockpit-item-count {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--calc-accent);
}

.cockpit-headline {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 2px;
}
.cockpit-range-display {
    font-size: clamp(1.35rem, 2.2vw, 1.65rem);
    font-weight: 900;
    color: #ffffff;
    line-height: 1.2;
    margin-bottom: 10px;
    letter-spacing: -0.4px;
    font-feature-settings: "tnum";
}

.cockpit-sub-metrics {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 10px;
}
.sub-metric-box {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: var(--calc-radius-sm);
    padding: 6px 10px;
}
.sm-label {
    font-size: 0.64rem;
    font-weight: 700;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.65);
}
.sm-value {
    font-size: 0.88rem;
    font-weight: 800;
    color: #ffffff;
    font-feature-settings: "tnum";
}

.cockpit-target-banner {
    background: rgba(184, 243, 90, 0.16);
    border: 1px solid rgba(184, 243, 90, 0.4);
    border-radius: var(--calc-radius-sm);
    padding: 7px 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: var(--calc-accent);
    font-size: 0.77rem;
    font-weight: 800;
}
.cockpit-target-banner span.val {
    font-size: 0.91rem;
    font-weight: 900;
}

.cockpit-controls {
    background: var(--calc-slate-50);
    padding: 12px 18px;
    border-bottom: 1px solid var(--calc-slate-200);
    display: flex;
    flex-direction: column;
    gap: 10px;
    box-sizing: border-box;
}
.currency-segmented-control {
    display: flex;
    background: var(--calc-slate-200);
    padding: 2px;
    border-radius: var(--calc-radius-sm);
}
.currency-segment-btn {
    flex: 1;
    background: transparent;
    border: none;
    padding: 5px 8px;
    border-radius: 6px;
    font-size: 0.77rem;
    font-weight: 800;
    color: var(--calc-slate-600);
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: center;
}
.currency-segment-btn.active {
    background: #ffffff;
    color: var(--calc-primary);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.discount-adjuster-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.discount-slider-wrap {
    flex: 1;
}
.discount-slider-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--calc-slate-600);
    margin-bottom: 2px;
}
.discount-slider-wrap input[type=range] {
    width: 100%;
    accent-color: var(--calc-primary);
    cursor: pointer;
}
.discount-number-input {
    width: 52px;
    padding: 4px 6px;
    border-radius: 6px;
    border: 1px solid var(--calc-slate-300);
    text-align: center;
    font-weight: 800;
    font-size: 0.81rem;
    color: var(--calc-dark);
}

.cockpit-items-scroll {
    padding: 12px 16px;
    max-height: 260px;
    overflow-y: auto;
    box-sizing: border-box;
}
.cockpit-items-scroll::-webkit-scrollbar {
    width: 4px;
}
.cockpit-items-scroll::-webkit-scrollbar-thumb {
    background: var(--calc-slate-300);
    border-radius: 10px;
}

.cockpit-item-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 7px 0;
    border-bottom: 1px solid var(--calc-slate-100);
    font-size: 0.79rem;
    gap: 8px;
}
.cockpit-item-row:last-child {
    border-bottom: none;
}
.cir-info {
    flex: 1;
    min-width: 0;
}
.cir-name {
    font-weight: 700;
    color: var(--calc-dark);
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cir-meta {
    font-size: 0.69rem;
    color: var(--calc-slate-500);
}
.cir-price {
    font-weight: 800;
    color: var(--calc-primary);
    text-align: right;
    white-space: nowrap;
}
.cir-remove-btn {
    background: none;
    border: none;
    color: var(--calc-slate-400);
    cursor: pointer;
    padding: 2px 4px;
    font-size: 0.75rem;
}
.cir-remove-btn:hover {
    color: #ef4444;
}

.cockpit-empty-state {
    text-align: center;
    padding: 22px 10px;
    color: var(--calc-slate-400);
}
.cockpit-empty-state i {
    font-size: 2rem;
    color: var(--calc-slate-300);
    margin-bottom: 6px;
}

.cockpit-footer {
    padding: 14px 18px 18px;
    background: #ffffff;
    border-top: 1px solid var(--calc-slate-200);
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-sizing: border-box;
}
.cockpit-delivery-tag {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    padding: 6px 10px;
    border-radius: var(--calc-radius-sm);
    font-size: 0.74rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-align: center;
}

.cockpit-action-btn {
    width: 100%;
    padding: 10px 16px;
    border-radius: var(--calc-radius-sm);
    font-size: 0.86rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    border: none;
    transition: all 0.15s ease;
    text-decoration: none;
    box-sizing: border-box;
}
.btn-cockpit-quote {
    background: var(--calc-primary);
    color: #ffffff;
}
.btn-cockpit-quote:hover {
    background: var(--calc-primary-hover);
    color: #ffffff;
}
.btn-cockpit-whatsapp {
    background: #25d366;
    color: #ffffff;
}
.btn-cockpit-whatsapp:hover {
    background: #20bd5a;
    color: #ffffff;
}
.btn-cockpit-print {
    background: var(--calc-slate-100);
    color: var(--calc-slate-700);
    border: 1px solid var(--calc-slate-300);
}
.btn-cockpit-print:hover {
    background: var(--calc-slate-200);
    color: var(--calc-dark);
}

/* 10. Mobile Sticky Floating Bottom Pill */
.mobile-sticky-capsule {
    display: none;
    position: fixed;
    bottom: 12px;
    left: 12px;
    right: 12px;
    background: rgba(2, 68, 66, 0.96);
    backdrop-filter: blur(10px);
    color: #ffffff;
    padding: 10px 16px;
    border-radius: 50px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    z-index: 999;
    align-items: center;
    justify-content: space-between;
}
@media (max-width: 1080px) {
    .mobile-sticky-capsule {
        display: flex;
    }
}
.mobile-range-text {
    font-size: 1.05rem;
    font-weight: 900;
    color: #ffffff;
}

/* 11. Modals */
.calc-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(5px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}
.calc-modal-backdrop.open {
    display: flex;
}
.calc-modal-box {
    background: #ffffff;
    border-radius: var(--calc-radius-lg);
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    box-sizing: border-box;
}
.calc-modal-head {
    padding: 16px 20px;
    border-bottom: 1px solid var(--calc-slate-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.calc-modal-head h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--calc-dark);
    display: flex;
    align-items: center;
    gap: 8px;
}
.calc-modal-close-btn {
    background: none;
    border: none;
    font-size: 1.2rem;
    color: var(--calc-slate-400);
    cursor: pointer;
}
.calc-modal-body {
    padding: 18px 20px;
    box-sizing: border-box;
}
.calc-modal-foot {
    padding: 12px 20px;
    background: var(--calc-slate-50);
    border-top: 1px solid var(--calc-slate-200);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
.wa-preview-bubble {
    background: #f8fafc;
    border: 1px solid var(--calc-slate-300);
    border-radius: var(--calc-radius-sm);
    padding: 14px;
    font-family: monospace;
    font-size: 0.81rem;
    color: var(--calc-dark);
    white-space: pre-wrap;
    max-height: 330px;
    overflow-y: auto;
    line-height: 1.5;
}

/* 12. Print Stylesheet */
@media print {
    body {
        background: #fff !important;
        color: #000 !important;
        font-size: 11pt !important;
    }
    #header, #nav-bar, .calc-hero-banner, .presets-shelf, .toolbar-capsule, .calc-cockpit-sidebar,
    .mobile-sticky-capsule, .calc-modal-backdrop, .stepper-btn, .cir-remove-btn, .meta-field-select {
        display: none !important;
    }
    .height-100, .calc-container {
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
    }
    .calc-workspace {
        display: block !important;
    }
    .print-only-sheet-header {
        display: block !important;
        margin-bottom: 25px;
        border-bottom: 2px solid #024442;
        padding-bottom: 15px;
    }
    .print-items-table {
        display: table !important;
        width: 100% !important;
        border-collapse: collapse !important;
        margin-top: 15px !important;
    }
    .print-items-table th, .print-items-table td {
        border: 1px solid #ddd !important;
        padding: 8px 10px !important;
        text-align: left !important;
    }
    .print-items-table th {
        background-color: #f1f5f9 !important;
        font-weight: bold !important;
    }
    .calc-section-card {
        display: none !important;
    }
}
.print-only-sheet-header, .print-items-table {
    display: none;
}
</style>

<div class="height-100">
<div class="calc-container">

    <!-- Print Only Header -->
    <div class="print-only-sheet-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="color: #024442; margin: 0 0 4px 0; font-size: 22pt;">GRAPHICAFIX</h1>
                <p style="margin: 0; color: #555; font-size: 10pt;">Project Scope Scoping & Quotation Estimate</p>
            </div>
            <div style="text-align: right; font-size: 9pt; color: #555;">
                <p style="margin: 0;"><strong>Date:</strong> <?= date('F d, Y') ?></p>
                <p style="margin: 0;"><strong>Prepared By:</strong> <?= htmlspecialchars($name) ?> (<?= ucfirst($role) ?>)</p>
            </div>
        </div>
        <div style="margin-top: 15px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
            <p style="margin: 0 0 4px 0;"><strong>Client / Company:</strong> <span id="printClientDisplay">—</span></p>
            <p style="margin: 0;"><strong>Project Name:</strong> <span id="printProjectDisplay">—</span></p>
        </div>
    </div>

    <!-- Standard Page Header -->
    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:20px;">
        <div>
            <h1 style="font-size:1.6rem; font-weight:700; color:#024442; margin:0 0 4px 0;"><i class="fas fa-calculator"></i> Price Calculator</h1>
            <p style="margin:0; color:#64748b; font-size:14px;">Select website scope and features to calculate real-time client price ranges.</p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <button type="button" class="btn btn-secondary" onclick="resetAllSelections()"><i class="fas fa-redo"></i> Reset</button>
            <button type="button" class="btn btn-secondary" onclick="openWhatsAppModal()"><i class="fab fa-whatsapp" style="color: #25d366;"></i> WhatsApp Pitch</button>
            <button type="button" class="btn btn-primary" onclick="openTransferModal()"><i class="fas fa-file-invoice"></i> Create Quotation</button>
        </div>
    </div>

    <!-- 2. Quick Presets Shelf -->
    <div class="card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; margin-bottom:16px; box-shadow:0 1px 3px rgba(15,23,42,0.05);">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; white-space:nowrap;">
                <i class="fas fa-bolt" style="color:#d97706;"></i> Quick Packages:
            </span>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <button type="button" class="btn btn-sm btn-secondary preset-btn" onclick="applyPreset('landing')">Landing Page</button>
                <button type="button" class="btn btn-sm btn-secondary preset-btn" onclick="applyPreset('business_standard')">Business 5-Page</button>
                <button type="button" class="btn btn-sm btn-secondary preset-btn" onclick="applyPreset('corporate')">Custom Corporate</button>
                <button type="button" class="btn btn-sm btn-secondary preset-btn" onclick="applyPreset('ecommerce')">WooCommerce Store</button>
                <button type="button" class="btn btn-sm btn-secondary preset-btn" onclick="applyPreset('lms')">LMS & Education</button>
                <button type="button" class="btn btn-sm btn-secondary preset-btn" onclick="applyPreset('saas')">SaaS MVP</button>
            </div>
        </div>
    </div>

    <!-- 3. Client & Project Setup Strip -->
    <div class="card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px 20px; margin-bottom:18px; box-shadow:0 1px 3px rgba(15,23,42,0.05);">
        <div class="row g-3">
            <div class="col-md-3 col-sm-6">
                <label class="form-label" style="font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;"><i class="fas fa-user-check" style="color:#024442;"></i> CRM Client (Optional)</label>
                <select class="form-select form-select-sm" id="clientSelect" onchange="onClientSelect(this)">
                    <option value="">— New Prospective Client —</option>
                    <?php foreach ($clients_list as $cl): ?>
                        <option value="<?= $cl['id'] ?>" 
                                data-name="<?= htmlspecialchars($cl['client_name']) ?>"
                                data-company="<?= htmlspecialchars($cl['company']) ?>"
                                data-email="<?= htmlspecialchars($cl['email']) ?>"
                                data-phone="<?= htmlspecialchars($cl['phone']) ?>">
                            <?= htmlspecialchars($cl['client_name']) ?> <?= !empty($cl['company']) ? '('.htmlspecialchars($cl['company']).')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label" style="font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;"><i class="fas fa-user" style="color:#024442;"></i> Contact Person</label>
                <input type="text" class="form-control form-control-sm" id="clientNameInput" placeholder="e.g. Tariq Mehmood" oninput="updateSummaryDisplays()">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label" style="font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;"><i class="fas fa-building" style="color:#024442;"></i> Company / Brand</label>
                <input type="text" class="form-control form-control-sm" id="companyNameInput" placeholder="e.g. Apex Global Ltd" oninput="updateSummaryDisplays()">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label" style="font-size:12px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px;"><i class="fas fa-project-diagram" style="color:#024442;"></i> Project Scope Name</label>
                <input type="text" class="form-control form-control-sm" id="projectNameInput" placeholder="e.g. Corporate Website & Portal" oninput="updateSummaryDisplays()">
            </div>
        </div>
    </div>

    <!-- 4. Main Two-Column Layout -->
    <div class="calc-workspace">

        <!-- LEFT COLUMN: Module Categories List -->
        <div class="calc-catalog-pane">

            <!-- Search, Filter & Master Expand/Collapse Toolbar -->
            <div class="card" style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px 18px; margin-bottom:18px; box-shadow:0 1px 3px rgba(15,23,42,0.05);">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
                    <div style="position:relative; flex:1 1 250px;">
                        <i class="fas fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:13px;"></i>
                        <input type="text" id="moduleSearchInput" class="form-control form-control-sm" style="padding-left:34px; padding-right:32px; font-size:13px;" placeholder="Search modules, features, APIs (e.g. 'whatsapp', 'seo', 'dashboard')..." oninput="filterModules(this.value)">
                        <button type="button" id="searchClearBtn" onclick="clearSearch()" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); border:none; background:#e2e8f0; color:#475569; width:20px; height:20px; border-radius:50%; display:none; align-items:center; justify-content:center; cursor:pointer; font-size:10px;"><i class="fas fa-times"></i></button>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" id="btnToggleAll" onclick="toggleAllSections()">
                        <i class="fas fa-compress-alt"></i> <span>Collapse All</span>
                    </button>
                </div>
                <div class="tab-nav-custom" style="margin-bottom:0; padding-bottom:4px; gap:4px; border-bottom:1px solid #e2e8f0; overflow-x:auto;">
                    <button type="button" class="tab-btn-custom active" data-tab="all" onclick="filterCategoryTab('all')">
                        All <span class="badge bg-light text-dark" id="countAll" style="font-size:10px; margin-left:4px;">79</span>
                    </button>
                    <button type="button" class="tab-btn-custom" data-tab="secA" onclick="filterCategoryTab('secA')">
                        A. Foundations <span class="badge bg-light text-dark" id="pillCount_secA" style="font-size:10px; margin-left:4px;">11</span>
                    </button>
                    <button type="button" class="tab-btn-custom" data-tab="secB" onclick="filterCategoryTab('secB')">
                        B. Pages & Design <span class="badge bg-light text-dark" id="pillCount_secB" style="font-size:10px; margin-left:4px;">8</span>
                    </button>
                    <button type="button" class="tab-btn-custom" data-tab="secC" onclick="filterCategoryTab('secC')">
                        C. Business <span class="badge bg-light text-dark" id="pillCount_secC" style="font-size:10px; margin-left:4px;">10</span>
                    </button>
                    <button type="button" class="tab-btn-custom" data-tab="secD" onclick="filterCategoryTab('secD')">
                        D. Backend <span class="badge bg-light text-dark" id="pillCount_secD" style="font-size:10px; margin-left:4px;">10</span>
                    </button>
                    <button type="button" class="tab-btn-custom" data-tab="secE" onclick="filterCategoryTab('secE')">
                        E. E-commerce <span class="badge bg-light text-dark" id="pillCount_secE" style="font-size:10px; margin-left:4px;">12</span>
                    </button>
                    <button type="button" class="tab-btn-custom" data-tab="secF" onclick="filterCategoryTab('secF')">
                        F. LMS & Portals <span class="badge bg-light text-dark" id="pillCount_secF" style="font-size:10px; margin-left:4px;">13</span>
                    </button>
                    <button type="button" class="tab-btn-custom" data-tab="secG" onclick="filterCategoryTab('secG')">
                        G. Integrations <span class="badge bg-light text-dark" id="pillCount_secG" style="font-size:10px; margin-left:4px;">15</span>
                    </button>
                </div>
            </div>

            <!-- ==========================================
                 SECTION A: WEBSITE FOUNDATIONS (Base Type)
                 ========================================== -->
            <div class="calc-section-card" id="secA" data-category="secA">
                <div class="calc-section-header" onclick="toggleSection('secA')">
                    <div class="section-header-left">
                        <div class="section-icon-badge"><i class="fas fa-cubes"></i></div>
                        <div class="section-titles">
                            <h3>A. Website Foundations</h3>
                            <p>Choose 1 core website foundation (Single-select base)</p>
                        </div>
                    </div>
                    <div class="section-header-right">
                        <!-- Active Chips preview when collapsed -->
                        <div class="section-chips-preview" id="chips_secA"></div>
                        <div class="section-collapsed-summary" id="summaryPreview_secA"></div>
                        <span class="section-selected-pill" id="badge_secA">Selected</span>
                        <button type="button" class="section-toggle-btn" id="toggleBtn_secA" onclick="event.stopPropagation(); toggleSection('secA');">
                            <span>Hide Modules</span> <i class="fas fa-chevron-down section-chevron-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="calc-section-body">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
                        <span style="font-size:0.79rem; color:var(--calc-slate-500); font-weight:600;">Click any base card to select or switch core architecture</span>
                        <button type="button" class="preset-pill" style="padding:3px 10px; font-size:0.72rem;" onclick="clearBaseSelection(event)">
                            <i class="fas fa-times"></i> Clear Base
                        </button>
                    </div>

                    <div class="foundation-shelf-grid" id="foundationGrid">
                        <!-- Populated by JavaScript -->
                    </div>

                    <div class="advisory-callout" style="margin-top:16px;">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Shopify Platform Notice:</strong> The Shopify figures are proposed agency configuration & development rates, not a separately verified Shopify-specific market survey. Confirm platform subscriptions ($39/mo+) and payment gateway transaction fees independently with the client.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 SECTION B: ADDITIONAL PAGES AND DESIGN
                 ========================================== -->
            <div class="calc-section-card" id="secB" data-category="secB">
                <div class="calc-section-header" onclick="toggleSection('secB')">
                    <div class="section-header-left">
                        <div class="section-icon-badge"><i class="fas fa-layer-group"></i></div>
                        <div class="section-titles">
                            <h3>B. Additional Pages and Design</h3>
                            <p>Extra templates, custom UX screens, Figma prototypes, RTL & multilingual</p>
                        </div>
                    </div>
                    <div class="section-header-right">
                        <div class="section-chips-preview" id="chips_secB"></div>
                        <div class="section-collapsed-summary" id="summaryPreview_secB"></div>
                        <span class="section-selected-pill" id="badge_secB">0 Selected</span>
                        <button type="button" class="section-toggle-btn" id="toggleBtn_secB" onclick="event.stopPropagation(); toggleSection('secB');">
                            <span>Hide Modules</span> <i class="fas fa-chevron-down section-chevron-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="calc-section-body">
                    <div class="modules-shelf-grid" id="grid_secB"></div>
                </div>
            </div>

            <!-- ==========================================
                 SECTION C: BUSINESS FEATURES
                 ========================================== -->
            <div class="calc-section-card" id="secC" data-category="secC">
                <div class="calc-section-header" onclick="toggleSection('secC')">
                    <div class="section-header-left">
                        <div class="section-icon-badge"><i class="fas fa-briefcase"></i></div>
                        <div class="section-titles">
                            <h3>C. Business Features</h3>
                            <p>Contact forms, WhatsApp trigger, live chat, blog, portfolio, search filters</p>
                        </div>
                    </div>
                    <div class="section-header-right">
                        <div class="section-chips-preview" id="chips_secC"></div>
                        <div class="section-collapsed-summary" id="summaryPreview_secC"></div>
                        <span class="section-selected-pill" id="badge_secC">0 Selected</span>
                        <button type="button" class="section-toggle-btn" id="toggleBtn_secC" onclick="event.stopPropagation(); toggleSection('secC');">
                            <span>Hide Modules</span> <i class="fas fa-chevron-down section-chevron-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="calc-section-body">
                    <div class="modules-shelf-grid" id="grid_secC"></div>
                </div>
            </div>

            <!-- ==========================================
                 SECTION D: BACKEND AND USER MANAGEMENT
                 ========================================== -->
            <div class="calc-section-card" id="secD" data-category="secD">
                <div class="calc-section-header" onclick="toggleSection('secD')">
                    <div class="section-header-left">
                        <div class="section-icon-badge"><i class="fas fa-server"></i></div>
                        <div class="section-titles">
                            <h3>D. Backend and User Management</h3>
                            <p>Admin dashboards, user authentication, RBAC, CRUD modules, PDF generation</p>
                        </div>
                    </div>
                    <div class="section-header-right">
                        <div class="section-chips-preview" id="chips_secD"></div>
                        <div class="section-collapsed-summary" id="summaryPreview_secD"></div>
                        <span class="section-selected-pill" id="badge_secD">0 Selected</span>
                        <button type="button" class="section-toggle-btn" id="toggleBtn_secD" onclick="event.stopPropagation(); toggleSection('secD');">
                            <span>Hide Modules</span> <i class="fas fa-chevron-down section-chevron-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="calc-section-body">
                    <div class="modules-shelf-grid" id="grid_secD"></div>
                </div>
            </div>

            <!-- ==========================================
                 SECTION E: E-COMMERCE ADD-ONS
                 ========================================== -->
            <div class="calc-section-card" id="secE" data-category="secE">
                <div class="calc-section-header" onclick="toggleSection('secE')">
                    <div class="section-header-left">
                        <div class="section-icon-badge"><i class="fas fa-shopping-cart"></i></div>
                        <div class="section-titles">
                            <h3>E. E-commerce Add-ons</h3>
                            <p>Product catalogues, payment gateways, courier APIs, multi-vendor marketplace</p>
                        </div>
                    </div>
                    <div class="section-header-right">
                        <div class="section-chips-preview" id="chips_secE"></div>
                        <div class="section-collapsed-summary" id="summaryPreview_secE"></div>
                        <span class="section-selected-pill" id="badge_secE">0 Selected</span>
                        <button type="button" class="section-toggle-btn" id="toggleBtn_secE" onclick="event.stopPropagation(); toggleSection('secE');">
                            <span>Hide Modules</span> <i class="fas fa-chevron-down section-chevron-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="calc-section-body">
                    <div class="modules-shelf-grid" id="grid_secE"></div>
                </div>
            </div>

            <!-- ==========================================
                 SECTION F: BOOKING, PORTALS AND LMS
                 ========================================== -->
            <div class="calc-section-card" id="secF" data-category="secF">
                <div class="calc-section-header" onclick="toggleSection('secF')">
                    <div class="section-header-left">
                        <div class="section-icon-badge"><i class="fas fa-graduation-cap"></i></div>
                        <div class="section-titles">
                            <h3>F. Booking, Portals and LMS</h3>
                            <p>Customer & vendor portals, booking systems, LMS course catalogue, dashboards</p>
                        </div>
                    </div>
                    <div class="section-header-right">
                        <div class="section-chips-preview" id="chips_secF"></div>
                        <div class="section-collapsed-summary" id="summaryPreview_secF"></div>
                        <span class="section-selected-pill" id="badge_secF">0 Selected</span>
                        <button type="button" class="section-toggle-btn" id="toggleBtn_secF" onclick="event.stopPropagation(); toggleSection('secF');">
                            <span>Hide Modules</span> <i class="fas fa-chevron-down section-chevron-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="calc-section-body">
                    <div class="advisory-callout">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>LMS & SaaS Scoping Advisory:</strong> These are scoping estimates for individual modules, not market-verified prices. For a complete LMS or SaaS product, estimate the full architecture and workflows before committing to a fixed amount.
                        </div>
                    </div>
                    <div class="modules-shelf-grid" id="grid_secF"></div>
                </div>
            </div>

            <!-- ==========================================
                 SECTION G: INTEGRATIONS, SEO AND DEPLOYMENT
                 ========================================== -->
            <div class="calc-section-card" id="secG" data-category="secG">
                <div class="calc-section-header" onclick="toggleSection('secG')">
                    <div class="section-header-left">
                        <div class="section-icon-badge"><i class="fas fa-plug"></i></div>
                        <div class="section-titles">
                            <h3>G. Integrations, SEO and Deployment</h3>
                            <p>CRM sync, AI chatbot, WhatsApp API, SEO, speed optimization, maintenance</p>
                        </div>
                    </div>
                    <div class="section-header-right">
                        <div class="section-chips-preview" id="chips_secG"></div>
                        <div class="section-collapsed-summary" id="summaryPreview_secG"></div>
                        <span class="section-selected-pill" id="badge_secG">0 Selected</span>
                        <button type="button" class="section-toggle-btn" id="toggleBtn_secG" onclick="event.stopPropagation(); toggleSection('secG');">
                            <span>Hide Modules</span> <i class="fas fa-chevron-down section-chevron-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="calc-section-body">
                    <div class="advisory-callout">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Third-party Expenses:</strong> API access, external subscriptions (WhatsApp Meta / OpenAI), message charges, hosting and domain renewals are additional unless explicitly included in the formal agreement.
                        </div>
                    </div>
                    <div class="modules-shelf-grid" id="grid_secG"></div>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: Responsive Sticky Scope Estimator Sidebar -->
        <div class="calc-cockpit-sidebar" id="cockpitSidebar">
            <div class="card" style="background:#ffffff; border:1px solid #cbd5e1; border-radius:12px; box-shadow:0 4px 16px rgba(15,23,42,0.06); overflow:hidden;">
                
                <!-- Sidebar Header -->
                <div style="padding:16px 18px 12px 18px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h4 style="font-size:15px; font-weight:700; color:#024442; margin:0; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-clipboard-list" style="color:#024442;"></i> Scope Estimate
                        </h4>
                        <span style="font-size:11px; color:#64748b;">Live Scoping Calculation</span>
                    </div>
                    <span class="badge" id="sidebarSelectedCount" style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; font-weight:700; font-size:11px; padding:4px 8px;">0 items</span>
                </div>

                <!-- Price Range Showcase Box -->
                <div style="padding:16px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; margin-bottom:4px;">Estimated Range:</div>
                    <div id="totalRangeDisplay" style="font-size:1.45rem; font-weight:800; color:#024442; line-height:1.2; letter-spacing:-0.3px; font-feature-settings:'tnum';">
                        PKR 0 – 0
                    </div>

                    <!-- Min vs Max breakdown -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
                        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:6px 10px;">
                            <div style="font-size:10px; font-weight:700; text-transform:uppercase; color:#64748b;">Min Scope</div>
                            <div id="subMetricMin" style="font-size:13px; font-weight:700; color:#1e293b;">PKR 0</div>
                        </div>
                        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:6px 10px;">
                            <div style="font-size:10px; font-weight:700; text-transform:uppercase; color:#64748b;">Max Scope</div>
                            <div id="subMetricMax" style="font-size:13px; font-weight:700; color:#1e293b;">PKR 0</div>
                        </div>
                    </div>

                    <!-- Target Recommendation Pitch -->
                    <div id="targetMidpointDisplay" style="margin-top:10px; padding:7px 10px; background:#f0fbf9; border:1px solid #a7f3d0; border-radius:6px; display:flex; justify-content:space-between; align-items:center; font-size:12px;">
                        <span style="font-weight:600; color:#065f46;"><i class="fas fa-bullseye" style="color:#024442; margin-right:4px;"></i> Target Pitch:</span>
                        <strong id="targetMidpointVal" style="font-weight:800; color:#024442; font-size:13px;">PKR 0</strong>
                    </div>
                </div>

                <!-- Currency & Discount Adjuster -->
                <div style="padding:12px 18px; border-bottom:1px solid #f1f5f9;">
                    <!-- Currency Toggle (Existing universal button group) -->
                    <div class="btn-group w-100" style="margin-bottom:10px;">
                        <button type="button" class="btn btn-sm btn-primary currency-segment-btn active" id="btnCurPKR" onclick="changeCurrency('PKR')" style="font-size:12px; font-weight:700;">PKR (Rs)</button>
                        <button type="button" class="btn btn-sm btn-secondary currency-segment-btn" id="btnCurUSD" onclick="changeCurrency('USD')" style="font-size:12px; font-weight:700;">USD ($)</button>
                    </div>

                    <!-- Discount Slider & Input -->
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:11px; font-weight:700; text-transform:uppercase; color:#475569; margin-bottom:4px;">
                            <span>Discount: <span id="discountLabelVal" style="color:#024442;">0%</span></span>
                            <div style="display:flex; align-items:center; gap:3px;">
                                <input type="number" class="form-control form-control-sm" id="discountPercentInput" min="0" max="90" value="0" style="width:52px; height:26px; padding:2px 4px; text-align:center; font-weight:700; font-size:12px;" oninput="syncDiscount(this.value, 'input')">
                                <span style="font-size:11px; color:#64748b;">%</span>
                            </div>
                        </div>
                        <input type="range" id="discountRangeInput" min="0" max="50" step="1" value="0" style="width:100%; accent-color:#024442; cursor:pointer;" oninput="syncDiscount(this.value, 'slider')">
                    </div>
                </div>

                <!-- Scrollable Selected Items List -->
                <div class="cockpit-items-scroll" id="selectedItemsContainer" style="padding:10px 18px; max-height:240px; overflow-y:auto;">
                    <div class="cockpit-empty-state" id="emptyStateMsg" style="text-align:center; padding:18px 10px; color:#94a3b8;">
                        <i class="fas fa-calculator" style="font-size:24px; color:#cbd5e1; margin-bottom:8px;"></i>
                        <p style="margin:0; font-weight:600; font-size:13px; color:#64748b;">No modules selected</p>
                        <span style="font-size:11px; color:#94a3b8;">Pick a foundation or add modules</span>
                    </div>
                </div>

                <!-- Sidebar Footer & Actions -->
                <div style="padding:14px 18px; background:#fafafa; border-top:1px solid #f1f5f9;">
                    <div id="timelineEstimate" style="font-size:12px; color:#64748b; margin-bottom:12px; display:flex; align-items:center; gap:6px;">
                        <i class="fas fa-clock text-muted"></i> Est. Timeline: <strong style="color:#1e293b;">Select services</strong>
                    </div>

                    <button type="button" class="btn btn-primary w-100" style="padding:9px 14px; font-weight:700; font-size:13px; margin-bottom:8px; display:flex; align-items:center; justify-content:center; gap:8px;" onclick="openTransferModal()">
                        <i class="fas fa-file-invoice"></i> Create Formal Quotation
                    </button>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn btn-secondary btn-sm flex-fill" style="display:flex; align-items:center; justify-content:center; gap:5px;" onclick="openWhatsAppModal()">
                            <i class="fab fa-whatsapp" style="color:#25d366;"></i> Pitch
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm flex-fill" style="display:flex; align-items:center; justify-content:center; gap:5px;" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Print Summary Table (Visible only when printing) -->
    <table class="print-items-table" id="printSummaryTable">
        <thead>
            <tr>
                <th style="width: 50%;">Service / Module Scope</th>
                <th style="width: 15%;">Quantity</th>
                <th style="width: 17%;">Min Price</th>
                <th style="width: 18%;">Max Price</th>
            </tr>
        </thead>
        <tbody id="printTableBody"></tbody>
        <tfoot>
            <tr style="background:#f8fafc; font-weight:bold;">
                <td colspan="2" style="text-align:right;">Subtotal Range:</td>
                <td id="printSubMin">0</td>
                <td id="printSubMax">0</td>
            </tr>
            <tr id="printDiscountRow" style="display:none; color:#dc2626;">
                <td colspan="2" style="text-align:right;">Discount Applied:</td>
                <td colspan="2" id="printDiscountVal">—</td>
            </tr>
            <tr style="background:#024442; color:#fff; font-size:12pt; font-weight:bold;">
                <td colspan="2" style="text-align:right; color:#fff;">Total Proposed Scope:</td>
                <td id="printTotalMin" style="color:#fff;">0</td>
                <td id="printTotalMax" style="color:#fff;">0</td>
            </tr>
        </tfoot>
    </table>

</div>
</div>

<!-- Mobile Bottom Sticky Floating Capsule -->
<div class="mobile-sticky-capsule" onclick="scrollToCockpit()">
    <div>
        <div style="font-size: 0.65rem; text-transform: uppercase; color: rgba(255,255,255,0.75);">Total Investment:</div>
        <div class="mobile-range-text" id="mobilePriceDisplay">PKR 0 – 0</div>
    </div>
    <div style="display:flex; gap:6px;" onclick="event.stopPropagation()">
        <button type="button" class="hero-btn hero-btn-accent" style="padding:6px 12px; font-size:0.78rem;" onclick="openTransferModal()">
            <i class="fas fa-file-invoice"></i> Quote
        </button>
        <button type="button" class="hero-btn hero-btn-glass" style="padding:6px 10px; font-size:0.78rem;" onclick="openWhatsAppModal()">
            <i class="fab fa-whatsapp" style="color:#25d366;"></i>
        </button>
    </div>
</div>

<!-- ==========================================
     MODAL: WhatsApp / Email Proposal Summary
     ========================================== -->
<div class="calc-modal-backdrop" id="waModalBackdrop">
    <div class="calc-modal-box">
        <div class="calc-modal-head">
            <h4><i class="fab fa-whatsapp" style="color:#25d366;"></i> WhatsApp / Email Proposal Pitch</h4>
            <button type="button" class="calc-modal-close-btn" onclick="closeWhatsAppModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="calc-modal-body">
            <p style="font-size:0.86rem; color:var(--calc-slate-500); margin-top:0;">
                The client pitch below is pre-formatted with scope details, pricing brackets, and standard agency terms. Ready to copy and send:
            </p>
            <div class="wa-preview-bubble" id="proposalTextPreview"></div>
        </div>
        <div class="calc-modal-foot">
            <button type="button" class="hero-btn hero-btn-glass" style="color:var(--calc-dark); border-color:var(--calc-slate-300);" onclick="closeWhatsAppModal()">Close</button>
            <button type="button" class="hero-btn hero-btn-accent" onclick="copyProposalToClipboard()">
                <i class="fas fa-copy"></i> Copy to Clipboard
            </button>
            <button type="button" class="hero-btn btn-cockpit-whatsapp" onclick="launchWhatsAppDirect()">
                <i class="fab fa-whatsapp"></i> Open in WhatsApp
            </button>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL: Transfer Items to Quotation Builder
     ========================================== -->
<div class="calc-modal-backdrop" id="transferModalBackdrop">
    <div class="calc-modal-box">
        <div class="calc-modal-head">
            <h4><i class="fas fa-file-signature" style="color:var(--calc-primary);"></i> Transfer to Formal Quotation</h4>
            <button type="button" class="calc-modal-close-btn" onclick="closeTransferModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="calc-modal-body">
            <p style="font-size:0.86rem; color:var(--calc-slate-500); margin-top:0;">
                Transferring <strong id="transferCountDisplay">0 items</strong> directly into the Graphicafix Quotation Builder. Select the billing rate tier to populate into the quotation table:
            </p>

            <div style="display:flex; flex-direction:column; gap:10px; margin: 18px 0;">
                <label style="border: 2px solid var(--calc-primary); border-radius:var(--calc-radius-sm); padding:14px; background:var(--calc-primary-surface); cursor:pointer; display:flex; align-items:center; gap:12px;">
                    <input type="radio" name="transferTier" value="target" checked style="accent-color:var(--calc-primary); width:18px; height:18px;">
                    <div>
                        <div style="font-weight:800; color:var(--calc-dark); font-size:0.92rem;">
                            Recommended Target Price (Midpoint)
                            <span style="background:var(--calc-accent); color:#0c1a17; font-size:0.68rem; font-weight:800; padding:2px 7px; border-radius:10px; margin-left:6px;">Recommended</span>
                        </div>
                        <div style="font-size:0.8rem; color:var(--calc-slate-500); margin-top:2px;" id="transferTargetSum">PKR 0</div>
                    </div>
                </label>

                <label style="border: 1.5px solid var(--calc-slate-200); border-radius:var(--calc-radius-sm); padding:14px; cursor:pointer; display:flex; align-items:center; gap:12px;">
                    <input type="radio" name="transferTier" value="min" style="accent-color:var(--calc-primary); width:18px; height:18px;">
                    <div>
                        <div style="font-weight:800; color:var(--calc-dark); font-size:0.92rem;">Minimum Competitive Rate</div>
                        <div style="font-size:0.8rem; color:var(--calc-slate-500); margin-top:2px;" id="transferMinSum">PKR 0</div>
                    </div>
                </label>

                <label style="border: 1.5px solid var(--calc-slate-200); border-radius:var(--calc-radius-sm); padding:14px; cursor:pointer; display:flex; align-items:center; gap:12px;">
                    <input type="radio" name="transferTier" value="max" style="accent-color:var(--calc-primary); width:18px; height:18px;">
                    <div>
                        <div style="font-weight:800; color:var(--calc-dark); font-size:0.92rem;">Maximum Premium Scope Rate</div>
                        <div style="font-size:0.8rem; color:var(--calc-slate-500); margin-top:2px;" id="transferMaxSum">PKR 0</div>
                    </div>
                </label>
            </div>

            <div style="background:var(--calc-slate-50); border:1px solid var(--calc-slate-200); border-radius:var(--calc-radius-sm); padding:10px 12px; font-size:0.8rem; color:var(--calc-slate-600);">
                <i class="fas fa-info-circle text-primary"></i> The Quotation Builder will open in the next screen with all line items, quantities, client metadata, and deliverable descriptions auto-filled.
            </div>
        </div>
        <div class="calc-modal-foot">
            <button type="button" class="hero-btn hero-btn-glass" style="color:var(--calc-dark); border-color:var(--calc-slate-300);" onclick="closeTransferModal()">Cancel</button>
            <button type="button" class="hero-btn hero-btn-accent" onclick="proceedTransferToQuotation()">
                Proceed to Quotation Form →
            </button>
        </div>
    </div>
</div>

<!-- ==========================================================================
   13. DATA MATRIX & CALCULATOR JAVASCRIPT ENGINE
   ========================================================================== -->
<script>
// Complete pricing dataset exactly per agency specification
const PRICING_CATALOG = {
    // -------------------------------------------------------------
    // A. Website Foundations
    // -------------------------------------------------------------
    secA: [
        { id: "wf_landing_template", name: "Landing page, template-based", min: 25000, max: 40000, icon: "fas fa-file-code", badge: "Template Base", desc: "Turnkey high performance layout tailored for quick campaign launches.", days: 4 },
        { id: "wf_landing_custom", name: "Landing page, custom-designed", min: 40000, max: 75000, icon: "fas fa-paint-brush", badge: "Custom UX/UI", desc: "Bespoke custom UI/UX design crafted for brand storytelling & conversions.", days: 7 },
        { id: "wf_biz_5", name: "Business website, up to 5 pages", min: 60000, max: 100000, icon: "fas fa-globe", badge: "Most Popular", desc: "Core corporate presence: Home, About Us, Services, Portfolio, Contact Us.", days: 10 },
        { id: "wf_biz_10", name: "Business website, 6–10 pages", min: 90000, max: 180000, icon: "fas fa-building", badge: "Comprehensive", desc: "Expanded business presence with dedicated sub-service pages and case studies.", days: 14 },
        { id: "wf_corp_custom", name: "Custom corporate website", min: 150000, max: 250000, icon: "fas fa-landmark", badge: "Enterprise", desc: "Enterprise branding, custom micro-interactions, career & investor portals.", days: 21 },
        { id: "wf_wp_standard", name: "WordPress website, standard", min: 45000, max: 85000, icon: "fab fa-wordpress", badge: "WordPress CMS", desc: "Standard WordPress CMS setup with Elementor or Gutenberg block editor.", days: 8 },
        { id: "wf_shopify_standard", name: "Shopify store, standard setup", min: 80000, max: 150000, icon: "fab fa-shopify", badge: "Shopify Setup", desc: "Shopify theme customization, payment/shipping configuration, ready for inventory.", days: 12 },
        { id: "wf_wooc_standard", name: "WooCommerce store, standard", min: 90000, max: 180000, icon: "fas fa-store", badge: "WooCommerce", desc: "Self-hosted WooCommerce store with full code ownership and no platform commissions.", days: 14 },
        { id: "wf_ecom_custom", name: "E-commerce with custom integrations", min: 180000, max: 450000, icon: "fas fa-shopping-bag", badge: "Custom E-Com", desc: "Custom checkout flows, multiple couriers, ERP sync & warehouse inventory.", days: 28 },
        { id: "wf_custom_webapp", name: "Custom web application", min: 250000, max: 650000, icon: "fas fa-laptop-code", badge: "Full-Stack Web App", desc: "Dynamic tailored full-stack application (PHP/Laravel/Node/React) with specialized workflows.", days: 40 },
        { id: "wf_custom_saas", name: "Custom SaaS MVP", min: 400000, max: 900000, icon: "fas fa-cloud", badge: "Scalable SaaS MVP", desc: "Multi-tenant cloud SaaS architecture, subscription engine & core MVP product.", days: 60 }
    ],

    // -------------------------------------------------------------
    // B. Additional Pages and Design
    // -------------------------------------------------------------
    secB: [
        { id: "b_std_page", name: "Additional standard page", min: 4000, max: 7000, hasQty: true, unit: "page", defaultQty: 1, desc: "Standard informational page following established design templates.", days: 1 },
        { id: "b_custom_page", name: "Custom-designed page", min: 8000, max: 15000, hasQty: true, unit: "page", defaultQty: 1, desc: "Unique custom layout requiring tailored UI components and wireframing.", days: 2 },
        { id: "b_add_landing", name: "Additional landing page", min: 10000, max: 20000, hasQty: true, unit: "page", defaultQty: 1, desc: "Independent promotional campaign page with targeted CTA flow.", days: 3 },
        { id: "b_uiux_screens", name: "UI/UX design, up to 5 unique screens", min: 25000, max: 45000, hasQty: false, desc: "High-fidelity Figma wireframes and visual design screens with typography & color tokens.", days: 5 },
        { id: "b_figma_proto", name: "Figma clickable prototype", min: 15000, max: 30000, hasQty: false, desc: "Interactive clickable prototype for investor pitches or user-testing validation.", days: 3 },
        { id: "b_redesign", name: "Website redesign", min: 35000, max: 100000, hasQty: false, desc: "Complete visual, navigational, and responsive redesign of an existing website.", days: 10 },
        { id: "b_rtl_urdu", name: "RTL / Urdu layout adaptation", min: 10000, max: 25000, hasQty: false, desc: "Right-to-left layout alignment, Nastaliq typography setup, and bilingual CSS.", days: 4 },
        { id: "b_add_lang", name: "Additional language implementation", min: 10000, max: 20000, hasQty: true, unit: "lang", defaultQty: 1, desc: "Multi-language string mapping, route prefixing, and localized content sync per language.", days: 3 }
    ],

    // -------------------------------------------------------------
    // C. Business Features
    // -------------------------------------------------------------
    secC: [
        { id: "c_contact_form", name: "Contact form", min: 3000, max: 5000, hasQty: false, desc: "Clean contact form with validation, email dispatches, and anti-spam verification.", days: 1 },
        { id: "c_adv_inquiry", name: "Advanced inquiry form", min: 6000, max: 12000, hasQty: false, desc: "Dynamic conditional fields, file uploads, and auto-responder confirmation emails.", days: 2 },
        { id: "c_multistep_form", name: "Multi-step application form", min: 12000, max: 25000, hasQty: false, desc: "Progress-tracked multi-step wizard with client draft saving.", days: 3 },
        { id: "c_whatsapp_chat", name: "WhatsApp click-to-chat", min: 1500, max: 3000, hasQty: false, desc: "Floating WhatsApp widget with pre-filled greeting message.", days: 0.5 },
        { id: "c_live_chat", name: "Live chat widget setup", min: 2000, max: 5000, hasQty: false, desc: "Integration with Tidio, Crisp, or Tawk.to including mobile notifications setup.", days: 1 },
        { id: "c_blog_module", name: "Blog module", min: 10000, max: 20000, hasQty: false, desc: "Content publishing system with categories, author tags, and comment moderation.", days: 3 },
        { id: "c_portfolio_module", name: "Portfolio / case studies module", min: 8000, max: 15000, hasQty: false, desc: "Filterable work grid, project details modal, and client testimonial sliders.", days: 2 },
        { id: "c_adv_search", name: "Advanced search and filters", min: 10000, max: 25000, hasQty: false, desc: "Instant AJAX keyword search with multi-attribute filtering.", days: 4 },
        { id: "c_newsletter", name: "Newsletter integration", min: 5000, max: 10000, hasQty: false, desc: "Mailchimp / Brevo newsletter signup modal, automated welcome series.", days: 1 },
        { id: "c_multilingual", name: "Multilingual functionality", min: 10000, max: 25000, hasQty: false, desc: "Language switcher dropdown, translation database architecture & localized routing.", days: 3 }
    ],

    // -------------------------------------------------------------
    // D. Backend and User Management
    // -------------------------------------------------------------
    secD: [
        { id: "d_basic_admin", name: "Basic admin dashboard", min: 25000, max: 40000, hasQty: false, desc: "Secure dashboard to view incoming inquiries, manage leads and edit text content.", days: 5 },
        { id: "d_custom_admin", name: "Custom admin dashboard", min: 40000, max: 80000, hasQty: false, desc: "Tailored enterprise dashboard with analytical metrics, audit logs & workflow management.", days: 10 },
        { id: "d_user_auth", name: "User registration and login", min: 12000, max: 20000, hasQty: false, desc: "User sign-up, password hashing, email verification, and forgotten password resets.", days: 3 },
        { id: "d_profile_mgmt", name: "User profile management", min: 8000, max: 15000, hasQty: false, desc: "Member profile editing, avatar uploads, and password management.", days: 2 },
        { id: "d_rbac", name: "Role-based permissions", min: 12000, max: 25000, hasQty: false, desc: "Granular access matrices (Super Admin, Manager, Editor, Viewer).", days: 4 },
        { id: "d_crud_entity", name: "CRUD module per data entity", min: 10000, max: 20000, hasQty: true, unit: "entity", defaultQty: 1, desc: "Complete Create, Read, Update, Delete data table module with sorting and search.", days: 3 },
        { id: "d_notifications", name: "In-app notifications", min: 12000, max: 25000, hasQty: false, desc: "Notification center bell icon, unread status counters, and activity triggers.", days: 3 },
        { id: "d_export_csv", name: "Reporting and CSV/Excel export", min: 10000, max: 20000, hasQty: false, desc: "Custom report filters with one-click Excel and CSV data file download.", days: 2 },
        { id: "d_pdf_gen", name: "PDF generation", min: 8000, max: 15000, hasQty: false, desc: "Dynamic branded PDF invoice, certificate, or statement rendering.", days: 2 },
        { id: "d_approval_wf", name: "Approval workflow", min: 20000, max: 45000, hasQty: false, desc: "Multi-step request submission, reviewer approval/rejection, and email alerts.", days: 5 }
    ],

    // -------------------------------------------------------------
    // E. E-commerce Add-ons
    // -------------------------------------------------------------
    secE: [
        { id: "e_catalogue_setup", name: "Product catalogue setup", min: 8000, max: 15000, hasQty: false, desc: "Category hierarchy tree, product badges, breadcrumbs and grid layout templates.", days: 2 },
        { id: "e_upload_products", name: "Upload 25 additional products", min: 3000, max: 5000, hasQty: true, unit: "batch", defaultQty: 1, desc: "Product data entry, image optimization, SKU setup, and SEO tags per 25 items.", days: 1 },
        { id: "e_product_variants", name: "Product variants", min: 6000, max: 12000, hasQty: false, desc: "Color, size, material variant matrix with individual pricing and stock tracking.", days: 2 },
        { id: "e_cart", name: "Shopping cart", min: 8000, max: 15000, hasQty: false, desc: "Slide-out mini-cart, persistent user sessions, and instant coupon code input.", days: 2 },
        { id: "e_checkout", name: "Checkout customization", min: 8000, max: 15000, hasQty: false, desc: "Optimized single-page checkout flow with shipping address validation.", days: 3 },
        { id: "e_cod", name: "Cash on Delivery configuration", min: 2000, max: 4000, hasQty: false, desc: "COD checkout option configuration with confirmation notes.", days: 1 },
        { id: "e_payment_gateway", name: "Payment gateway integration", min: 15000, max: 30000, hasQty: false, desc: "Integration with PayFast, JazzCash, EasyPaisa, Stripe, or PayPal via webhook.", days: 4 },
        { id: "e_courier_api", name: "Courier API integration", min: 20000, max: 40000, hasQty: false, desc: "Automated consignment booking and tracking with TCS, Leopards, Trax, or PostEx.", days: 5 },
        { id: "e_inventory", name: "Inventory management", min: 12000, max: 25000, hasQty: false, desc: "Automatic stock deduction upon order, low-inventory notifications.", days: 3 },
        { id: "e_coupons", name: "Coupons and discount rules", min: 6000, max: 12000, hasQty: false, desc: "Percentage and fixed-amount coupon codes, usage limits, and minimum cart thresholds.", days: 2 },
        { id: "e_order_mgmt", name: "Order management customization", min: 10000, max: 20000, hasQty: false, desc: "Custom order lifecycle statuses, packing slip printing, and order email alerts.", days: 3 },
        { id: "e_multivendor", name: "Multi-vendor marketplace", min: 100000, max: 250000, hasQty: false, desc: "Independent vendor shops, vendor onboarding, commission splits, and vendor dashboards.", days: 25 }
    ],

    // -------------------------------------------------------------
    // F. Booking, Portals and LMS
    // -------------------------------------------------------------
    secF: [
        { id: "f_appointment_booking", name: "Appointment booking", min: 20000, max: 35000, hasQty: false, desc: "Online calendar appointment booking with timezone sync and email confirmations.", days: 5 },
        { id: "f_staff_availability", name: "Staff availability and booking", min: 35000, max: 60000, hasQty: false, desc: "Multi-staff calendar management, shift hours, and automatic staff allocation.", days: 7 },
        { id: "f_customer_portal", name: "Customer portal", min: 35000, max: 70000, hasQty: false, desc: "Self-service client dashboard for viewing projects, deliverables, and invoices.", days: 8 },
        { id: "f_vendor_portal", name: "Vendor portal", min: 60000, max: 120000, hasQty: false, desc: "External vendor area to submit quotes, deliverables, and payment receipts.", days: 12 },
        { id: "f_job_portal", name: "Job listing and application system", min: 25000, max: 45000, hasQty: false, desc: "Careers board, CV uploads, candidate applicant tracking system (ATS).", days: 6 },
        { id: "f_event_reg", name: "Event registration", min: 12000, max: 25000, hasQty: false, desc: "Event ticketing, ticket limits, attendee registration lists, and barcode passes.", days: 3 },
        { id: "f_course_cat", name: "Course catalogue and enrollment", min: 25000, max: 45000, hasQty: false, desc: "Course syllabus display, instructor profiles, and student course enrollments.", days: 6 },
        { id: "f_lesson_mgmt", name: "Lesson management", min: 20000, max: 35000, hasQty: false, desc: "Video streaming lessons, lesson progression locks, and resource file downloads.", days: 5 },
        { id: "f_quiz_system", name: "Quiz and scoring system", min: 25000, max: 50000, hasQty: false, desc: "Timed MCQs quizzes, automated grading algorithms, and score history.", days: 6 },
        { id: "f_assignment_sub", name: "Assignment submission", min: 15000, max: 30000, hasQty: false, desc: "Student file submission portal, instructor grading interface & feedback notes.", days: 4 },
        { id: "f_certificates", name: "Certificates", min: 10000, max: 20000, hasQty: false, desc: "Automated completion certificate generation with unique QR verification codes.", days: 3 },
        { id: "f_dashboards_st", name: "Student/teacher dashboards", min: 30000, max: 60000, hasQty: true, unit: "dashboard", defaultQty: 1, desc: "Dedicated portal dashboards (Student progress overview or Teacher grading hub).", days: 7 },
        { id: "f_multitenant_school", name: "Multi-school / tenant functionality", min: 75000, max: 200000, hasQty: false, desc: "Isolated institutional domains and separate database schemas for multi-campus setup.", days: 20 }
    ],

    // -------------------------------------------------------------
    // G. Integrations, SEO and Deployment
    // -------------------------------------------------------------
    secG: [
        { id: "g_smtp_email", name: "SMTP / business email configuration", min: 3000, max: 6000, hasQty: false, desc: "DKIM, SPF, and DMARC DNS records with transactional SMTP email delivery.", days: 1 },
        { id: "g_crm_integration", name: "Zoho CRM or HubSpot integration", min: 15000, max: 35000, hasQty: false, desc: "Two-way contact and deal pipeline synchronization with CRM.", days: 4 },
        { id: "g_zapier_make", name: "Zapier / Make workflow", min: 8000, max: 20000, hasQty: false, desc: "Automated webhook pipelines connecting form events to 1,000+ cloud tools.", days: 2 },
        { id: "g_gsheets", name: "Google Sheets integration", min: 8000, max: 15000, hasQty: false, desc: "Instant sync of web form submissions directly into Google Sheets spreadsheet.", days: 2 },
        { id: "g_rest_api_std", name: "Standard REST API integration", min: 15000, max: 30000, hasQty: false, desc: "Third-party REST API connection with JSON endpoints and authentication.", days: 4 },
        { id: "g_rest_api_cpx", name: "Complex API integration", min: 35000, max: 75000, hasQty: false, desc: "Heavy two-way sync, webhook listeners, retry mechanisms, or SOAP/XML APIs.", days: 8 },
        { id: "g_wa_business_api", name: "WhatsApp Business API integration", min: 25000, max: 60000, hasQty: false, desc: "Official Meta Cloud API integration for automated transactional alerts and OTPs.", days: 6 },
        { id: "g_ai_chatbot", name: "Basic AI chatbot", min: 25000, max: 50000, hasQty: false, desc: "Custom-trained AI chatbot on company FAQ dataset using OpenAI/Claude API.", days: 6 },
        { id: "g_ai_workflow", name: "Custom AI workflow", min: 40000, max: 100000, hasQty: false, desc: "Automated AI document analysis, content generator, or recommendation system.", days: 10 },
        { id: "g_seo_onpage", name: "Basic on-page SEO", min: 8000, max: 15000, hasQty: false, desc: "Meta tags, OpenGraph social preview tags, header tag hierarchy, and ALT text.", days: 2 },
        { id: "g_seo_technical", name: "Technical SEO setup", min: 10000, max: 20000, hasQty: false, desc: "Dynamic XML sitemap, robots.txt, canonical URLs, and schema.org JSON-LD.", days: 2 },
        { id: "g_speed_opt", name: "Website speed optimization", min: 15000, max: 35000, hasQty: false, desc: "Asset minification, WebP image conversion, Redis caching, 90+ PageSpeed target.", days: 3 },
        { id: "g_prod_deploy", name: "Standard production deployment", min: 8000, max: 15000, hasQty: false, desc: "Server setup (VPS/cPanel), SSL certificate installation, and domain cutover.", days: 2 },
        { id: "g_security", name: "Security hardening", min: 10000, max: 25000, hasQty: false, desc: "Web application firewall (WAF), brute-force defense, and secure header configuration.", days: 2 },
        { id: "g_maintenance", name: "Basic monthly maintenance", min: 10000, max: 20000, hasQty: true, unit: "month", defaultQty: 1, desc: "Regular core & plugin updates, weekly offsite backups, and uptime monitoring.", days: 0 }
    ]
};

// State Management
let selectedBaseId = null;
let selectedModules = {};
let currentCurrency = 'PKR';
const USD_RATE = 280;
let isAllCollapsed = false;

// -------------------------------------------------------------
// INITIALIZATION
// -------------------------------------------------------------
document.addEventListener("DOMContentLoaded", function() {
    renderFoundations();
    renderAllModuleSections();
    calculateTotals();
});

// Render Foundation Cards (Section A)
function renderFoundations() {
    const container = document.getElementById("foundationGrid");
    if (!container) return;
    container.innerHTML = "";

    PRICING_CATALOG.secA.forEach(item => {
        const card = document.createElement("div");
        card.className = "foundation-tier-card" + (selectedBaseId === item.id ? " selected" : "");
        card.id = "card_" + item.id;
        card.onclick = () => selectBaseFoundation(item.id);

        card.innerHTML = `
            <div>
                <div class="tier-card-top">
                    <div class="tier-icon-wrap"><i class="${item.icon}"></i></div>
                    <div class="tier-radio-indicator"></div>
                </div>
                <span class="tier-badge">${item.badge}</span>
                <div class="tier-title">${item.name}</div>
                <div class="tier-desc">${item.desc}</div>
            </div>
            <div class="tier-bottom-bar">
                <div class="tier-price-capsule">${formatCurrency(item.min)} – ${formatCurrency(item.max)}</div>
                <div class="tier-timeline-tag"><i class="fas fa-bolt text-warning"></i> ~${item.days}d</div>
            </div>
        `;
        container.appendChild(card);
    });
}

// Render All Module Grid Sections (B through G)
function renderAllModuleSections() {
    const sections = ["secB", "secC", "secD", "secE", "secF", "secG"];
    sections.forEach(secKey => {
        const grid = document.getElementById("grid_" + secKey);
        if (!grid) return;
        grid.innerHTML = "";

        PRICING_CATALOG[secKey].forEach(item => {
            const isSelected = !!selectedModules[item.id];
            const currentQty = selectedModules[item.id]?.qty || item.defaultQty || 1;

            const mod = document.createElement("div");
            mod.className = "module-unit-card" + (isSelected ? " selected" : "");
            mod.id = "mod_" + item.id;
            mod.dataset.name = item.name.toLowerCase();
            mod.dataset.desc = item.desc.toLowerCase();
            mod.onclick = (e) => {
                if (!e.target.closest('.mod-stepper') && !e.target.closest('.mod-toggle-switch')) {
                    toggleModule(item.id, secKey, !isSelected);
                }
            };

            let qtyHtml = "";
            if (item.hasQty) {
                qtyHtml = `
                    <div class="mod-stepper" onclick="event.stopPropagation()">
                        <button type="button" class="stepper-btn" onclick="adjustQty('${item.id}', -1)">-</button>
                        <input type="text" class="stepper-val" id="qty_${item.id}" value="${currentQty}" readonly>
                        <button type="button" class="stepper-btn" onclick="adjustQty('${item.id}', 1)">+</button>
                        <span class="stepper-unit">${item.unit}${currentQty > 1 ? 's' : ''}</span>
                    </div>
                `;
            }

            mod.innerHTML = `
                <div>
                    <div class="mod-card-top">
                        <div class="mod-card-details">
                            <h4 class="mod-unit-title">${item.name}</h4>
                            <p class="mod-unit-desc">${item.desc}</p>
                        </div>
                        <label class="mod-toggle-switch" onclick="event.stopPropagation()">
                            <input type="checkbox" id="check_${item.id}" ${isSelected ? "checked" : ""} onchange="toggleModule('${item.id}', '${secKey}', this.checked)">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="mod-card-bottom">
                    <div class="mod-price-pill">
                        ${formatCurrency(item.min)} – ${formatCurrency(item.max)}
                        ${item.hasQty ? '<span class="mod-unit-note">/' + item.unit + '</span>' : ''}
                    </div>
                    ${qtyHtml}
                </div>
            `;
            grid.appendChild(mod);
        });
    });
}

// -------------------------------------------------------------
// SELECTION HANDLERS
// -------------------------------------------------------------
function selectBaseFoundation(id) {
    if (selectedBaseId === id) {
        selectedBaseId = null;
    } else {
        selectedBaseId = id;
    }
    renderFoundations();
    calculateTotals();
}

function clearBaseSelection(e) {
    if (e) e.stopPropagation();
    selectedBaseId = null;
    renderFoundations();
    calculateTotals();
}

function toggleModule(id, secKey, isChecked) {
    const item = PRICING_CATALOG[secKey].find(x => x.id === id);
    if (!item) return;

    if (isChecked) {
        const qty = item.hasQty ? (parseInt(document.getElementById("qty_" + id)?.value) || item.defaultQty || 1) : 1;
        selectedModules[id] = {
            id: item.id,
            name: item.name,
            min: item.min,
            max: item.max,
            qty: qty,
            unit: item.unit || 'pcs',
            hasQty: item.hasQty,
            days: item.days || 1,
            secKey: secKey
        };
    } else {
        delete selectedModules[id];
    }

    const card = document.getElementById("mod_" + id);
    const chk = document.getElementById("check_" + id);
    if (card) {
        if (isChecked) card.classList.add("selected");
        else card.classList.remove("selected");
    }
    if (chk) chk.checked = isChecked;

    calculateTotals();
}

function adjustQty(id, delta) {
    let current = parseInt(document.getElementById("qty_" + id)?.value) || 1;
    let newVal = Math.max(1, current + delta);
    const input = document.getElementById("qty_" + id);
    if (input) input.value = newVal;

    if (selectedModules[id]) {
        selectedModules[id].qty = newVal;
        calculateTotals();
    }
}

// -------------------------------------------------------------
// COLLAPSIBLE ACCORDION & FILTER CONTROLS
// -------------------------------------------------------------
function toggleSection(secId) {
    const el = document.getElementById(secId);
    if (!el) return;
    const isNowCollapsed = el.classList.toggle("collapsed");
    updateSectionToggleBtnText(secId, isNowCollapsed);
}

function updateSectionToggleBtnText(secId, isCollapsed) {
    const btn = document.getElementById("toggleBtn_" + secId);
    if (!btn) return;
    const span = btn.querySelector("span");
    if (span) {
        span.textContent = isCollapsed ? "Show Modules" : "Hide Modules";
    }
}

function toggleAllSections() {
    isAllCollapsed = !isAllCollapsed;
    const sections = ["secA", "secB", "secC", "secD", "secE", "secF", "secG"];
    sections.forEach(secId => {
        const el = document.getElementById(secId);
        if (el) {
            if (isAllCollapsed) {
                el.classList.add("collapsed");
            } else {
                el.classList.remove("collapsed");
            }
            updateSectionToggleBtnText(secId, isAllCollapsed);
        }
    });

    const btn = document.getElementById("btnToggleAll");
    if (btn) {
        btn.innerHTML = isAllCollapsed 
            ? '<i class="fas fa-expand-alt"></i> <span>Expand All</span>' 
            : '<i class="fas fa-compress-alt"></i> <span>Collapse All</span>';
    }
}

function filterCategoryTab(tabKey) {
    document.querySelectorAll(".tab-btn-custom, .cat-pill-btn").forEach(b => {
        b.classList.toggle("active", b.getAttribute("data-tab") === tabKey);
    });

    const sections = ["secA", "secB", "secC", "secD", "secE", "secF", "secG"];
    sections.forEach(secId => {
        const el = document.getElementById(secId);
        if (!el) return;
        if (tabKey === 'all') {
            el.style.display = "block";
        } else if (tabKey === secId) {
            el.style.display = "block";
            el.classList.remove("collapsed");
            updateSectionToggleBtnText(secId, false);
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            el.style.display = "none";
        }
    });
}

function scrollToCockpit() {
    const el = document.getElementById("cockpitSidebar");
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
}

// -------------------------------------------------------------
// CALCULATION ENGINE & COLLAPSED HEADER CHIPS BUILDER
// -------------------------------------------------------------
function calculateTotals() {
    let totalMin = 0;
    let totalMax = 0;
    let totalDays = 0;
    let itemCount = 0;

    const summaryContainer = document.getElementById("selectedItemsContainer");
    summaryContainer.innerHTML = "";

    // 1. Base Foundation (Section A)
    const secAEl = document.getElementById("secA");
    const summarySecA = document.getElementById("summaryPreview_secA");
    const badgeSecA = document.getElementById("badge_secA");
    const chipsSecA = document.getElementById("chips_secA");

    if (selectedBaseId) {
        const base = PRICING_CATALOG.secA.find(x => x.id === selectedBaseId);
        if (base) {
            totalMin += base.min;
            totalMax += base.max;
            totalDays += base.days || 7;
            itemCount++;

            appendSummaryRow({
                name: base.name,
                isBase: true,
                min: base.min,
                max: base.max,
                qty: 1,
                onRemove: () => clearBaseSelection()
            });

            if (secAEl) secAEl.classList.add("has-active-items");
            if (badgeSecA) {
                badgeSecA.innerHTML = '<i class="fas fa-check"></i> Selected';
                badgeSecA.style.display = "inline-block";
            }
            if (summarySecA) {
                summarySecA.textContent = `${formatCurrency(base.min)} – ${formatCurrency(base.max)}`;
                summarySecA.classList.add("has-data");
            }
            if (chipsSecA) {
                chipsSecA.innerHTML = `
                    <span class="section-preview-chip" title="${base.name}">
                        <i class="fas fa-check-circle text-success"></i> ${base.name}
                        <button type="button" class="chip-remove-btn" title="Remove Base" onclick="event.stopPropagation(); clearBaseSelection();">×</button>
                    </span>
                `;
                chipsSecA.classList.add("has-chips");
            }
            const pillSecA = document.getElementById("pillCount_secA");
            if (pillSecA) {
                pillSecA.textContent = "1 selected";
                pillSecA.classList.add("has-selection");
            }
        }
    } else {
        if (secAEl) secAEl.classList.remove("has-active-items");
        if (badgeSecA) badgeSecA.style.display = "none";
        if (summarySecA) {
            summarySecA.textContent = "";
            summarySecA.classList.remove("has-data");
        }
        if (chipsSecA) {
            chipsSecA.innerHTML = "";
            chipsSecA.classList.remove("has-chips");
        }
        const pillSecA = document.getElementById("pillCount_secA");
        if (pillSecA) {
            pillSecA.textContent = PRICING_CATALOG.secA.length;
            pillSecA.classList.remove("has-selection");
        }
    }

    // 2. Add-on Modules (Sections B through G)
    const secCounts = { secB: 0, secC: 0, secD: 0, secE: 0, secF: 0, secG: 0 };
    const secCost = { secB: [0,0], secC: [0,0], secD: [0,0], secE: [0,0], secF: [0,0], secG: [0,0] };
    const secSelectedItems = { secB: [], secC: [], secD: [], secE: [], secF: [], secG: [] };

    Object.values(selectedModules).forEach(item => {
        const itemMin = item.min * item.qty;
        const itemMax = item.max * item.qty;
        totalMin += itemMin;
        totalMax += itemMax;
        totalDays += (item.days || 1) * Math.min(item.qty, 4) * 0.4;
        itemCount++;

        if (secCounts[item.secKey] !== undefined) {
            secCounts[item.secKey]++;
            secCost[item.secKey][0] += itemMin;
            secCost[item.secKey][1] += itemMax;
            secSelectedItems[item.secKey].push(item);
        }

        appendSummaryRow({
            name: item.name,
            isBase: false,
            min: itemMin,
            max: itemMax,
            qty: item.hasQty ? item.qty + " " + item.unit + (item.qty > 1 ? "s" : "") : null,
            onRemove: () => {
                toggleModule(item.id, item.secKey, false);
            }
        });
    });

    // Update Section Badge counts, Preview Summaries, and Interactive Header Chips
    Object.keys(secCounts).forEach(secKey => {
        const el = document.getElementById(secKey);
        const badge = document.getElementById("badge_" + secKey);
        const pillCount = document.getElementById("pillCount_" + secKey);
        const summary = document.getElementById("summaryPreview_" + secKey);
        const chipsContainer = document.getElementById("chips_" + secKey);

        const count = secCounts[secKey];
        if (count > 0) {
            if (el) el.classList.add("has-active-items");
            if (badge) {
                badge.innerHTML = `<i class="fas fa-check"></i> ${count} Selected`;
                badge.style.display = "inline-block";
            }
            if (pillCount) {
                pillCount.textContent = `${count} selected`;
                pillCount.classList.add("has-selection");
            }
            if (summary) {
                summary.textContent = `${count} items • ${formatCurrency(secCost[secKey][0])} – ${formatCurrency(secCost[secKey][1])}`;
                summary.classList.add("has-data");
            }
            if (chipsContainer) {
                let chipsHtml = "";
                secSelectedItems[secKey].slice(0, 4).forEach(it => {
                    const qtyLabel = it.hasQty ? ` (${it.qty})` : '';
                    chipsHtml += `
                        <span class="section-preview-chip" title="${it.name}">
                            ${it.name}${qtyLabel}
                            <button type="button" class="chip-remove-btn" title="Remove" onclick="event.stopPropagation(); toggleModule('${it.id}', '${secKey}', false);">×</button>
                        </span>
                    `;
                });
                if (secSelectedItems[secKey].length > 4) {
                    chipsHtml += `<span class="section-preview-chip">+${secSelectedItems[secKey].length - 4} more</span>`;
                }
                chipsContainer.innerHTML = chipsHtml;
                chipsContainer.classList.add("has-chips");
            }
        } else {
            if (el) el.classList.remove("has-active-items");
            if (badge) badge.style.display = "none";
            if (pillCount) {
                pillCount.textContent = PRICING_CATALOG[secKey].length;
                pillCount.classList.remove("has-selection");
            }
            if (summary) {
                summary.textContent = "";
                summary.classList.remove("has-data");
            }
            if (chipsContainer) {
                chipsContainer.innerHTML = "";
                chipsContainer.classList.remove("has-chips");
            }
        }
    });

    // Handle Empty State
    if (itemCount === 0) {
        summaryContainer.innerHTML = `
            <div class="cockpit-empty-state">
                <i class="fas fa-calculator"></i>
                <p style="margin:0; font-weight:700; color:var(--calc-slate-600);">No modules selected</p>
                <span style="font-size:0.75rem;">Pick a base foundation or toggle add-ons to calculate</span>
            </div>
        `;
    }

    // Handle Discount
    const discPct = Math.min(90, Math.max(0, parseFloat(document.getElementById("discountPercentInput").value) || 0));
    const finalMin = totalMin * (1 - (discPct / 100));
    const finalMax = totalMax * (1 - (discPct / 100));
    const midpoint = (finalMin + finalMax) / 2;

    // Display formatted strings
    const rangeStr = `${formatCurrency(finalMin)} – ${formatCurrency(finalMax)}`;
    document.getElementById("totalRangeDisplay").textContent = rangeStr;
    document.getElementById("mobilePriceDisplay").textContent = rangeStr;
    document.getElementById("sidebarSelectedCount").textContent = itemCount + (itemCount === 1 ? " item" : " items");
    
    document.getElementById("subMetricMin").textContent = formatCurrency(finalMin);
    document.getElementById("subMetricMax").textContent = formatCurrency(finalMax);
    document.getElementById("targetMidpointVal").textContent = formatCurrency(midpoint);

    // Delivery Turnaround Timeline calculation
    const timelineBadge = document.getElementById("timelineEstimate");
    if (itemCount === 0) {
        timelineBadge.innerHTML = `<i class="fas fa-clock"></i> Est. Turnaround: <strong>Select services</strong>`;
    } else {
        const daysCeil = Math.max(3, Math.ceil(totalDays));
        const weeks = (daysCeil / 6).toFixed(1);
        timelineBadge.innerHTML = `<i class="fas fa-clock"></i> Est. Turnaround: <strong>~${daysCeil} business days</strong> (${weeks} wks)`;
    }

    // Update Print Summary Table
    updatePrintSummaryTable(totalMin, totalMax, discPct, finalMin, finalMax);
}

function appendSummaryRow(data) {
    const row = document.createElement("div");
    row.className = "cockpit-item-row";
    row.innerHTML = `
        <div class="cir-info">
            <div class="cir-name">
                ${data.isBase ? '<span style="color:var(--calc-primary); font-weight:900;">[BASE] </span>' : ''}
                ${data.name}
            </div>
            ${data.qty ? '<div class="cir-meta">Qty: ' + data.qty + '</div>' : ''}
        </div>
        <div class="cir-price">${formatCurrency(data.min)} – ${formatCurrency(data.max)}</div>
        <button type="button" class="cir-remove-btn" title="Remove item"><i class="fas fa-times"></i></button>
    `;
    row.querySelector(".cir-remove-btn").onclick = data.onRemove;
    document.getElementById("selectedItemsContainer").appendChild(row);
}

// -------------------------------------------------------------
// CURRENCY & DISCOUNT SYNCS
// -------------------------------------------------------------
function formatCurrency(amountPKR) {
    if (currentCurrency === 'USD') {
        const inUSD = Math.round(amountPKR / USD_RATE);
        return '$' + inUSD.toLocaleString();
    }
    return 'PKR ' + Math.round(amountPKR).toLocaleString();
}

function changeCurrency(cur) {
    currentCurrency = cur;
    const btnPKR = document.getElementById("btnCurPKR");
    const btnUSD = document.getElementById("btnCurUSD");
    if (btnPKR && btnUSD) {
        btnPKR.classList.toggle("btn-primary", cur === 'PKR');
        btnPKR.classList.toggle("btn-secondary", cur !== 'PKR');
        btnPKR.classList.toggle("active", cur === 'PKR');
        btnUSD.classList.toggle("btn-primary", cur === 'USD');
        btnUSD.classList.toggle("btn-secondary", cur !== 'USD');
        btnUSD.classList.toggle("active", cur === 'USD');
    }
    renderFoundations();
    renderAllModuleSections();
    calculateTotals();
}

function syncDiscount(val, source) {
    let v = Math.min(50, Math.max(0, parseInt(val) || 0));
    document.getElementById("discountRangeInput").value = v;
    document.getElementById("discountPercentInput").value = v;
    document.getElementById("discountLabelVal").textContent = v + "%";
    calculateTotals();
}

// -------------------------------------------------------------
// FILTERING & SEARCH
// -------------------------------------------------------------
function filterModules(query) {
    const q = (query || "").toLowerCase().trim();
    const clearBtn = document.getElementById("searchClearBtn");
    if (clearBtn) clearBtn.style.display = q.length > 0 ? "flex" : "none";

    const sections = ["secA", "secB", "secC", "secD", "secE", "secF", "secG"];

    sections.forEach(secId => {
        const secEl = document.getElementById(secId);
        if (!secEl) return;
        const allCards = secEl.querySelectorAll(".module-unit-card, .foundation-tier-card");
        let secHasMatch = false;

        allCards.forEach(card => {
            const text = card.textContent.toLowerCase();
            if (!q || text.includes(q)) {
                card.style.display = "flex";
                secHasMatch = true;
            } else {
                card.style.display = "none";
            }
        });

        // Automatically expand section if search has matches
        if (q.length > 0 && secHasMatch) {
            secEl.classList.remove("collapsed");
            updateSectionToggleBtnText(secId, false);
        }
    });
}

function clearSearch() {
    const inp = document.getElementById("moduleSearchInput");
    if (inp) {
        inp.value = "";
        filterModules("");
    }
}

// -------------------------------------------------------------
// PRESETS
// -------------------------------------------------------------
function resetAllSelections() {
    selectedBaseId = null;
    selectedModules = {};
    syncDiscount(0, 'reset');
    document.querySelectorAll("input[type='checkbox']").forEach(c => c.checked = false);
    document.querySelectorAll(".preset-pill, .preset-btn").forEach(p => {
        p.classList.remove("active", "btn-primary");
        p.classList.add("btn-secondary");
    });
    renderFoundations();
    renderAllModuleSections();
    calculateTotals();
}

function applyPreset(presetType) {
    resetAllSelections();

    const clickedBtn = document.querySelector(`.preset-btn[onclick*="'${presetType}'"]`);
    if (clickedBtn) {
        clickedBtn.classList.add("btn-primary");
        clickedBtn.classList.remove("btn-secondary");
    }

    if (presetType === 'landing') {
        selectedBaseId = "wf_landing_template";
        activateModule("c_contact_form");
        activateModule("c_whatsapp_chat");
        activateModule("g_seo_onpage");
    } else if (presetType === 'business_standard') {
        selectedBaseId = "wf_biz_5";
        activateModule("c_contact_form");
        activateModule("c_whatsapp_chat");
        activateModule("c_blog_module");
        activateModule("g_seo_onpage");
        activateModule("g_speed_opt");
    } else if (presetType === 'corporate') {
        selectedBaseId = "wf_biz_10";
        activateModule("c_adv_inquiry");
        activateModule("c_whatsapp_chat");
        activateModule("c_blog_module");
        activateModule("c_portfolio_module");
        activateModule("d_basic_admin");
        activateModule("g_seo_onpage");
        activateModule("g_seo_technical");
        activateModule("g_speed_opt");
    } else if (presetType === 'ecommerce') {
        selectedBaseId = "wf_wooc_standard";
        activateModule("e_catalogue_setup");
        activateModule("e_product_variants");
        activateModule("e_cart");
        activateModule("e_checkout");
        activateModule("e_cod");
        activateModule("e_payment_gateway");
        activateModule("e_courier_api");
        activateModule("g_seo_onpage");
    } else if (presetType === 'lms') {
        selectedBaseId = "wf_biz_10";
        activateModule("d_user_auth");
        activateModule("d_profile_mgmt");
        activateModule("f_customer_portal");
        activateModule("f_course_cat");
        activateModule("f_lesson_mgmt");
        activateModule("f_quiz_system");
        activateModule("f_certificates");
        activateModule("f_dashboards_st", 2);
    } else if (presetType === 'saas') {
        selectedBaseId = "wf_custom_saas";
        activateModule("d_custom_admin");
        activateModule("d_user_auth");
        activateModule("d_rbac");
        activateModule("d_crud_entity", 4);
        activateModule("d_pdf_gen");
        activateModule("e_payment_gateway");
        activateModule("g_rest_api_std");
        activateModule("g_security");
    }

    renderFoundations();
    renderAllModuleSections();
    calculateTotals();

    // Auto expand active sections and scroll to Section A
    const sections = ["secA", "secB", "secC", "secD", "secE", "secF", "secG"];
    sections.forEach(secId => {
        const el = document.getElementById(secId);
        if (el && el.classList.contains("has-active-items")) {
            el.classList.remove("collapsed");
            updateSectionToggleBtnText(secId, false);
        }
    });

    document.getElementById("secA").scrollIntoView({ behavior: 'smooth' });
}

function activateModule(id, qty = 1) {
    let secFound = null;
    let itemFound = null;
    const sections = ["secB", "secC", "secD", "secE", "secF", "secG"];
    for (let s of sections) {
        const it = PRICING_CATALOG[s].find(x => x.id === id);
        if (it) {
            secFound = s;
            itemFound = it;
            break;
        }
    }
    if (itemFound) {
        selectedModules[id] = {
            id: itemFound.id,
            name: itemFound.name,
            min: itemFound.min,
            max: itemFound.max,
            qty: qty,
            unit: itemFound.unit || 'pcs',
            hasQty: itemFound.hasQty,
            days: itemFound.days || 1,
            secKey: secFound
        };
    }
}

// -------------------------------------------------------------
// CLIENT AUTO-FILL & DISPLAY SYNCS
// -------------------------------------------------------------
function onClientSelect(selectEl) {
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    if (selectedOpt && selectedOpt.value) {
        document.getElementById("clientNameInput").value = selectedOpt.getAttribute("data-name") || "";
        document.getElementById("companyNameInput").value = selectedOpt.getAttribute("data-company") || "";
    }
    updateSummaryDisplays();
}

function updateSummaryDisplays() {
    const cName = document.getElementById("clientNameInput").value.trim();
    const cComp = document.getElementById("companyNameInput").value.trim();
    const pName = document.getElementById("projectNameInput").value.trim();

    const clientStr = [cName, cComp ? `(${cComp})` : ''].filter(Boolean).join(' ') || 'Prospective Client';
    document.getElementById("printClientDisplay").textContent = clientStr;
    document.getElementById("printProjectDisplay").textContent = pName || 'Website & Web Application Development';
}

// -------------------------------------------------------------
// PRINT & PDF TABLE BUILDER
// -------------------------------------------------------------
function updatePrintSummaryTable(totalMin, totalMax, discPct, finalMin, finalMax) {
    const tbody = document.getElementById("printTableBody");
    tbody.innerHTML = "";

    if (selectedBaseId) {
        const base = PRICING_CATALOG.secA.find(x => x.id === selectedBaseId);
        if (base) {
            tbody.innerHTML += `
                <tr style="background:#f8fafc; font-weight:600;">
                    <td><strong>[Foundation Base]</strong> ${base.name}</td>
                    <td>1 base</td>
                    <td>${formatCurrency(base.min)}</td>
                    <td>${formatCurrency(base.max)}</td>
                </tr>
            `;
        }
    }

    Object.values(selectedModules).forEach(it => {
        const itMin = it.min * it.qty;
        const itMax = it.max * it.qty;
        tbody.innerHTML += `
            <tr>
                <td>${it.name}</td>
                <td>${it.hasQty ? it.qty + ' ' + it.unit + (it.qty > 1 ? 's' : '') : '1 module'}</td>
                <td>${formatCurrency(itMin)}</td>
                <td>${formatCurrency(itMax)}</td>
            </tr>
        `;
    });

    document.getElementById("printSubMin").textContent = formatCurrency(totalMin);
    document.getElementById("printSubMax").textContent = formatCurrency(totalMax);

    const discRow = document.getElementById("printDiscountRow");
    if (discPct > 0) {
        discRow.style.display = "table-row";
        document.getElementById("printDiscountVal").textContent = `-${discPct}% Discount Applied`;
    } else {
        discRow.style.display = "none";
    }

    document.getElementById("printTotalMin").textContent = formatCurrency(finalMin);
    document.getElementById("printTotalMax").textContent = formatCurrency(finalMax);
}

// -------------------------------------------------------------
// PROPOSAL & WHATSAPP GENERATION
// -------------------------------------------------------------
function generateProposalText() {
    const cName = document.getElementById("clientNameInput").value.trim() || "Valued Client";
    const cComp = document.getElementById("companyNameInput").value.trim();
    const pName = document.getElementById("projectNameInput").value.trim() || "Custom Web Development";
    
    let itemsText = [];
    let totalMin = 0;
    let totalMax = 0;
    let days = 0;

    if (selectedBaseId) {
        const base = PRICING_CATALOG.secA.find(x => x.id === selectedBaseId);
        if (base) {
            totalMin += base.min;
            totalMax += base.max;
            days += base.days || 7;
            itemsText.push(`• *Base Architecture:* ${base.name} (${formatCurrency(base.min)} – ${formatCurrency(base.max)})`);
        }
    }

    Object.values(selectedModules).forEach(it => {
        const itemMin = it.min * it.qty;
        const itemMax = it.max * it.qty;
        totalMin += itemMin;
        totalMax += itemMax;
        days += (it.days || 1) * Math.min(it.qty, 4) * 0.4;
        const qtyLabel = it.hasQty ? ` [Qty: ${it.qty} ${it.unit}]` : '';
        itemsText.push(`• ${it.name}${qtyLabel} (${formatCurrency(itemMin)} – ${formatCurrency(itemMax)})`);
    });

    const discPct = Math.min(90, Math.max(0, parseFloat(document.getElementById("discountPercentInput").value) || 0));
    const finalMin = totalMin * (1 - (discPct / 100));
    const finalMax = totalMax * (1 - (discPct / 100));
    const midpoint = (finalMin + finalMax) / 2;
    const estDays = Math.max(3, Math.ceil(days));

    let discountClause = discPct > 0 ? `\n🎁 *Special Discount Applied:* ${discPct}%\n` : '';

    return `*PROJECT SCOPE & INVESTMENT PROPOSAL*
━━━━━━━━━━━━━━━━━━━━━━━━━━
🏢 *Agency:* Graphicafix
👤 *Client:* ${cName} ${cComp ? `(${cComp})` : ''}
📂 *Project:* ${pName}
📅 *Date:* ${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}

*PROPOSED SCOPE OF WORK:*
${itemsText.length > 0 ? itemsText.join("\n") : "• Custom requirements scoping in progress"}
${discountClause}
💰 *TOTAL ESTIMATED INVESTMENT:*
*${formatCurrency(finalMin)} – ${formatCurrency(finalMax)}*
🎯 *Recommended Target Budget:* *${formatCurrency(midpoint)}*
⏱️ *Estimated Delivery:* *~${estDays} business days*

*KEY PROJECT NOTES:*
1. Production staging environment and quality assurance testing included.
2. 30 days post-launch technical warranty and bug support included.
3. Domain, server hosting, and 3rd-party subscription/API licenses are billed separately.

Let us know if you'd like to schedule a quick call to finalize requirements and initiate onboarding!`;
}

function openWhatsAppModal() {
    const text = generateProposalText();
    document.getElementById("proposalTextPreview").textContent = text;
    document.getElementById("waModalBackdrop").classList.add("open");
}

function closeWhatsAppModal() {
    document.getElementById("waModalBackdrop").classList.remove("open");
}

function copyProposalToClipboard() {
    const text = generateProposalText();
    navigator.clipboard.writeText(text).then(() => {
        alert("Proposal text copied to clipboard successfully!");
    }).catch(err => {
        alert("Failed to copy. Please manually copy from the preview box.");
    });
}

function launchWhatsAppDirect() {
    const text = encodeURIComponent(generateProposalText());
    window.open(`https://api.whatsapp.com/send?text=${text}`, '_blank');
}

// -------------------------------------------------------------
// TRANSFER TO FORMAL QUOTATION BUILDER
// -------------------------------------------------------------
function openTransferModal() {
    let count = (selectedBaseId ? 1 : 0) + Object.keys(selectedModules).length;
    if (count === 0) {
        alert("Please select at least 1 foundation or module to transfer into Quotation Builder.");
        return;
    }

    let totalMin = 0;
    let totalMax = 0;
    if (selectedBaseId) {
        const b = PRICING_CATALOG.secA.find(x => x.id === selectedBaseId);
        if (b) { totalMin += b.min; totalMax += b.max; }
    }
    Object.values(selectedModules).forEach(it => {
        totalMin += it.min * it.qty;
        totalMax += it.max * it.qty;
    });

    const discPct = Math.min(90, Math.max(0, parseFloat(document.getElementById("discountPercentInput").value) || 0));
    const finalMin = totalMin * (1 - (discPct / 100));
    const finalMax = totalMax * (1 - (discPct / 100));
    const midpoint = (finalMin + finalMax) / 2;

    document.getElementById("transferCountDisplay").textContent = count + (count === 1 ? " item" : " items");
    document.getElementById("transferTargetSum").textContent = `Calculated: ${formatCurrency(midpoint)}`;
    document.getElementById("transferMinSum").textContent = `Calculated: ${formatCurrency(finalMin)}`;
    document.getElementById("transferMaxSum").textContent = `Calculated: ${formatCurrency(finalMax)}`;

    document.getElementById("transferModalBackdrop").classList.add("open");
}

function closeTransferModal() {
    document.getElementById("transferModalBackdrop").classList.remove("open");
}

function proceedTransferToQuotation() {
    const tier = document.querySelector('input[name="transferTier"]:checked')?.value || 'target';
    const discPct = Math.min(90, Math.max(0, parseFloat(document.getElementById("discountPercentInput").value) || 0));

    const transferItems = [];
    const scopeDeliverables = [];

    function getTierPrice(minVal, maxVal) {
        let base = (minVal + maxVal) / 2;
        if (tier === 'min') base = minVal;
        else if (tier === 'max') base = maxVal;
        else base = (minVal + maxVal) / 2;

        if (discPct > 0) base = base * (1 - (discPct / 100));
        return Math.round(base);
    }

    if (selectedBaseId) {
        const b = PRICING_CATALOG.secA.find(x => x.id === selectedBaseId);
        if (b) {
            const price = getTierPrice(b.min, b.max);
            transferItems.push({
                name: b.name,
                description: `${b.name} - Core Architecture`,
                quantity: 1,
                unit: 'setup',
                price: price
            });
            scopeDeliverables.push({
                description: b.name,
                details: b.desc
            });
        }
    }

    Object.values(selectedModules).forEach(it => {
        const price = getTierPrice(it.min, it.max);
        transferItems.push({
            name: it.name,
            description: it.name,
            quantity: it.qty || 1,
            unit: it.unit || 'pcs',
            price: price
        });
        scopeDeliverables.push({
            description: it.name,
            details: `Implementation and deployment of ${it.name}`
        });
    });

    const quotationDraft = {
        clientId: document.getElementById("clientSelect").value || '',
        clientName: document.getElementById("clientNameInput").value.trim() || '',
        clientCompany: document.getElementById("companyNameInput").value.trim() || '',
        projectName: document.getElementById("projectNameInput").value.trim() || 'Website Development Project',
        currency: currentCurrency,
        items: transferItems,
        scopeDeliverables: scopeDeliverables,
        transferDate: new Date().toISOString()
    };

    sessionStorage.setItem("graphica_calc_quotation", JSON.stringify(quotationDraft));
    window.location.href = "create_quotation.php?from_calc=1";
}
</script>

<?php include 'dashboard_footer.php'; ?>
