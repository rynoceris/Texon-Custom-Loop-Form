# Custom Laundry Loops Form

A WordPress plugin for creating custom sublimated laundry loops with WooCommerce integration.

## Description

The Custom Laundry Loops Form plugin allows customers to order custom laundry loops with various options directly from your WooCommerce store. It provides a user-friendly form with color selection, clip types, logo upload, and personalization options.

## Features

- **Color Selection**: Multiple strap color options with visual preview
- **Clip Types**: Single and double clip configurations
- **Logo Upload**: Support for JPG, PNG, SVG, PDF, and AI file formats
- **Custom Text**: Add personalized text to loops
- **Numbering/Names**: Support for numbers or names on loops
- **Multiple Quantities**: Flexible quantity options
- **Custom Fonts**: Font selection and upload capability
- **Text Color**: Customizable text color options
- **Live Preview**: Real-time preview of loop configurations
- **WooCommerce Integration**: Seamless cart and checkout integration
- **Multi-Session Support**: Persistent cart across user sessions

## Requirements

- **WordPress**: 6.5+
- **WooCommerce**: 8.8.x+
- **PHP**: 8.1+

## Tested Compatibility

- **WordPress**: Up to 6.8.2
- **WooCommerce**: Up to 10.1.2  
- **PHP**: Up to 8.2.28

## Installation

1. Upload the plugin files to `/wp-content/plugins/custom-laundry-loops-form/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings in **Settings > Custom Loops Form**
4. Place the shortcode `[custom_laundry_loops_form]` on any page

## Configuration

### Admin Settings

Access plugin settings via **Settings > Custom Loops Form**:

- **Debug Mode**: Enable administrator debug information on cart page
- **Form Page Selection**: Choose the page containing the form shortcode
- **Sublimation Product IDs**: Configure pricing tiers for different quantities
- **Product Image IDs**: Set images for different loop colors and clip types

### Shortcode Usage

```
[custom_laundry_loops_form]
```

Place this shortcode on any page where you want the custom loops form to appear.

## Key Features

### Cart Management
- **Multi-Session Persistence**: Maintains cart contents across user sessions
- **Automatic Quantity Calculation**: Smart sublimation tag quantity management
- **Pricing Tiers**: Different pricing for quantities under/over 24 loops
- **Setup Fee Management**: Automatic one-time setup fee handling

### Form Functionality
- **Dynamic Preview**: Real-time visualization of loop configurations
- **File Upload**: Secure logo and custom font upload system
- **Validation**: Client and server-side form validation
- **Session Management**: Persistent form data across page reloads

### WooCommerce Integration
- **Custom Payment Gateway**: Specialized payment processing for custom loops
- **Cart Protection**: Prevents manual modification of quantities
- **Order Processing**: Seamless integration with WooCommerce checkout
- **Email Notifications**: Custom order confirmation emails

## Version History

### Version 2.3.3 - September 2025
- **Fixed**: Multi-session cart quantity calculation issues
- **Fixed**: Cart validation errors on checkout pages
- **Fixed**: Order confirmation quantity discrepancies
- **Improved**: Cart session persistence across multiple sessions
- **Enhanced**: Multi-layered cart fix system
- **Updated**: WordPress 6.8.2 and WooCommerce 10.1.2 compatibility

### Version 2.3.2 - July 2025
- **Fixed**: WooCommerce session saving bug diagnostics
- **Added**: Admin debug mode toggle
- **Enhanced**: Cart page diagnostic information

### Version 2.3.1 - June 2025
- **Added**: Shortcode page detection in admin settings

### Version 2.3 - June 2025
- **Enhanced**: Cart protection system with multi-product support
- **Improved**: Admin display functionality
- **Updated**: Email notification system
- **Enhanced**: Debug tools and cart page functionality

[View complete changelog](readme.html)

## Support

For support and feature requests:

- **Website**: [https://texontowel.com](https://texontowel.com)
- **Email**: sales@texontowel.com
- **Phone**: 1-800-329-3966

## Development

This plugin is actively developed and maintained by Texon Towel.

### Contributing
This is a proprietary plugin developed specifically for Texon Towel's custom laundry loop products.

### Security
The plugin includes robust security measures:
- Nonce verification for all form submissions
- File upload validation and sanitization  
- SQL injection prevention
- XSS protection
- Capability checks for admin functions

## License

**License**: GPL v3 or later  
**License URI**: [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

## Credits

- **Developer**: Ryan Ours
- **Copyright**: © 2025 Texon Towel
- **Contributors**: Texon Towel Team

---

**Built with ❤️ for the textile industry**