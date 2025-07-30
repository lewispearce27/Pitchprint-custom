# PitchPrint Integration for WooCommerce

A WordPress plugin that integrates PitchPrint web-to-print solution with WooCommerce, allowing customers to design products online or upload their own artwork.

## Features

- **Dual Button Options**: Choose between "Design Online", "Upload Artwork", or both buttons for each product
- **Design Online**: Opens PitchPrint designer with pre-selected templates
- **Upload Artwork**: Allows customers to upload their designs to a blank canvas
- **Product-Level Configuration**: Set different PitchPrint options for each product
- **Order Management**: View and download customer designs from order details
- **API Integration**: Secure connection to PitchPrint services

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.0 or higher
- PitchPrint account with API credentials

## Installation

1. Download the plugin files
2. Upload the `pitchprint-integration` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your API credentials in PitchPrint settings

## Configuration

### Initial Setup

1. Navigate to **PitchPrint** in your WordPress admin menu
2. Enter your **API Key** and **Secret Key** from your PitchPrint account
3. Click **Save Settings**
4. Use **Test Connection** to verify your credentials

### Product Configuration

1. Edit any WooCommerce product
2. Click the **PitchPrint** tab in the product data section
3. Select button type:
   - **None**: No PitchPrint functionality
   - **Design Online**: Shows design button with template
   - **Upload Artwork**: Shows upload button for blank canvas
   - **Both Buttons**: Shows both options
4. If using Design Online:
   - Click **Load Categories** to fetch design categories
   - Select a **Design Category**
   - Select a **Design Template**
5. Save the product

## Directory Structure

```
pitchprint-integration/
├── assets/
│   ├── css/
│   │   ├── admin.css          # Admin styles
│   │   └── frontend.css       # Frontend styles
│   └── js/
│       ├── admin.js           # Admin JavaScript
│       └── frontend.js        # Frontend JavaScript
├── includes/
│   ├── class-pitchprint-admin.php     # Admin settings class
│   ├── class-pitchprint-api.php       # API communication class
│   ├── class-pitchprint-frontend.php  # Frontend functionality
│   └── class-pitchprint-product.php   # Product settings class
├── languages/                          # Translation files
├── pitchprint-integration.php         # Main plugin file
└── README.md                          # This file
```

## Usage

### For Customers

1. Visit a product with PitchPrint enabled
2. Choose either:
   - **Design Online**: Customize the product using templates
   - **Upload Artwork**: Upload your own design file
3. Complete your design and save
4. Add the customized product to cart
5. Complete checkout as normal

### For Store Administrators

1. Configure products with appropriate PitchPrint options
2. View customer designs in order details
3. Download print-ready PDFs from orders
4. Manage API settings from main plugin page

## API Endpoints Used

- `POST /runtime/fetch-designs` - Retrieve designs by category
- `POST /runtime/fetch-project` - Get project details
- `POST /runtime/render-pdf` - Generate PDF files
- `POST /runtime/fetch-raster` - Download high-resolution images

## Hooks and Filters

### Actions
- `pitchprint_after_project_saved` - Fired after a project is saved
- `pitchprint_before_add_to_cart` - Fired before adding to cart

### Filters
- `pitchprint_button_types` - Modify available button types
- `pitchprint_customization_required` - Set if customization is mandatory
- `pitchprint_design_categories` - Filter design categories

## Troubleshooting

### Connection Issues
- Verify API credentials are correct
- Check if your PitchPrint account is active
- Ensure your server can make outbound HTTPS requests

### Design Not Loading
- Confirm design template is selected for the product
- Check browser console for JavaScript errors
- Verify jQuery is loaded on the page

### Upload Issues
- Check file size limits
- Verify allowed file types in PitchPrint settings
- Ensure proper server permissions

## Support

For issues related to:
- **Plugin functionality**: Create an issue in this repository
- **PitchPrint service**: Contact PitchPrint support
- **WooCommerce**: Refer to WooCommerce documentation

## License

This plugin is licensed under GPL v2 or later.

## Credits

Developed for integration with [PitchPrint](https://pitchprint.com) web-to-print solution.
