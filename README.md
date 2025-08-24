# Eseabasi Inventory Management Plugin

A comprehensive WordPress inventory management system for import, stock, and chopped items with real-time integration and analytics.

## Features

### Frontend Pages
1. **Import Form** - 2 columns (Product name dropdown with 45 products, Quantity input)
2. **Import History** - With filters and pagination
3. **Stock Form** - 5 columns (Products, Opening Packs, Added Packs, Used Packs, Closing Packs)
4. **Stock Record History** - With filters and pagination  
5. **Chopped Form** - 6 columns for 14 specific fruits only
6. **Chopped Record History** - With filters and pagination

### Design
- Main color theme: #FF0000 (red)
- Modern, sleek design
- Mobile responsive
- Lagos/Africa timezone for all timestamps

### Core Functionality
- Non-editable fields: Staff name, date, real-time timestamp, remarks
- Staff vs Admin permissions (staff limited editing, admin full access)
- Daily reset logic for values except opening/closing carry forward
- Real-time calculations with specific formulas
- Cumulative updates for same-day entries (add together, not replace)
- Decimal value support (3.20, 0.50)

### Admin Dashboard
- Product management (add/edit/delete products)
- History management (edit/delete/clear all records)
- Analytics dashboard with beautiful modern cards
- All buttons are functional

### Critical Integration Logic
- Fruit imports → Update "Import Whole" in chopped form (with persistence)
- Non-fruit imports → Update "Added Packs from chopped/imports" in stock form (with persistence)
- Chopped packs gotten → Update "Added Packs from chopped/imports" in stock form (with persistence)

### Additional Features
- Analytics dashboard with modern cards design
- CSV/PDF export functionality
- Input validation (no negatives)
- Low stock alerts with threshold monitoring
- Auto-save functionality
- Real-time clock updates

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create the required database tables
4. Access the admin interface via the 'Eseabasi Inventory' menu

## Shortcodes

Use these shortcodes to display forms and history pages:

- `[eseabasi_import_form]` - Import form
- `[eseabasi_import_history]` - Import history with filters
- `[eseabasi_stock_form]` - Stock form  
- `[eseabasi_stock_history]` - Stock history with filters
- `[eseabasi_chopped_form]` - Chopped form
- `[eseabasi_chopped_history]` - Chopped history with filters
- `[eseabasi_analytics]` - Analytics dashboard

## Database Tables

The plugin creates the following tables:
- `wp_eseabasi_products` - Store products for each form
- `wp_eseabasi_imports` - Import records
- `wp_eseabasi_stock` - Stock records
- `wp_eseabasi_chopped` - Chopped records
- `wp_eseabasi_current_values` - Current day values for forms
- `wp_eseabasi_integration_meta` - Track processed imports

## Products

### All Products (Import & Stock forms)
Almond, Apple, Baking Powder, Banana, Blueberry, Cake, Caramel/Chocolate Syrup, Cashew Nut, Cherry, Chia Seed, Cinnamon, Cocoa Powder, Coconut Flakes, Coffee, Condensed Milk, Cucumber, Dates, Egg, Evaporated Milk, Fresh Coconut, Ginger, Granola, Grape, Groundnuts, Honey, Ice Cream, Kiwi, Lemon, Lime, Maca Powder, Nut Packed, Nutri Choco, Oat, Oranges, Paw Paw, Peanut Butter, Peanuts, Pineapple, Powdered Milk, Pumpkin Seed, Raisin, Rapha Yoghurt, Strawberry, Sugar, Sunflower Seed, Tiger Nut, Watermelon, Whey Protein

### Fruits Only (Chopped form)
Almond, Cucumber, Dates, Fresh Coconut, Ginger, Grape, Ice Cream, Kiwi, Lemon, Lime, Paw Paw, Pineapple, Tiger Nut, Watermelon

## Permissions

### Staff Users
- Can enter quantities in import form
- Can edit "Used Packs" and remarks in stock form
- Can edit "Prepared (Whole)" and "Pack(s) Gotten" in chopped form
- Cannot edit opening/closing values or added/import values

### Admin Users
- Full access to all fields in all forms
- Product management capabilities
- History management capabilities
- Settings configuration

## Integration Rules

### Import to Chopped
When fruits are imported via the import form, their quantities automatically update the "Import (Whole)" column in the chopped form for corresponding fruits.

### Import to Stock
When non-fruit items are imported, their quantities automatically update the "Added Packs (from chopped/imports)" column in the stock form.

### Chopped to Stock
When "Pack(s) Gotten" values are entered in the chopped form, they automatically update the "Added Packs (from chopped/imports)" column in the stock form for fruits.

## Calculations

### Stock Form
Closing Packs = Opening Packs + Added Packs (from chopped/imports) - Used Packs

### Chopped Form  
Closing (Whole) = Opening (Whole) + Import (Whole) - Prepared (Whole)

## Daily Reset

Each day at midnight (Lagos time):
- All form values reset to 0 except opening and closing values
- Previous day's closing values become the new day's opening values
- Import and added values start fresh for cumulative updates

## Support

For support and customization, please contact the development team.

## Version

Current version: 1.0.0
