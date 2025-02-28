=== Dead Dove ===  
Contributors: jeremy-malcolm  
Donate link: https://buy.stripe.com/eVa6q4d3QboKexqaEE
Tags: content warning, sensitive content, shortcode, tags  
Requires at least: 5.0  
Tested up to: 6.6  
Stable tag: 1.2  
Requires PHP: 7.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Extend the WordPress tagging system to provide content warnings. Selected content will be blurred until the user reads and agrees to a disclaimer.

== Description ==

The **Dead Dove** plugin lets administrators apply content warnings, which are defined in a custom taxonomy of terms. Content tagged with certain terms will be blurred from view and require users to read disclaimers and click their agreement to access it. Administrators define which terms trigger warnings and can provide custom warning texts in the term descriptions. 

Users can also customize their own term-based content warning settings, overriding the admin’s defaults. A shortcode feature allows you to apply warnings to specific blocks of content. If multiple warning terms apply, all relevant warnings will be shown.

The plugin is highly flexible and supports user preferences as well as dynamic content warnings applied via shortcode and BuddyBoss media uploads.

### **Features**  
- Blur content based on assigned terms and display warning text before viewing.  
- Administrators select which terms require warnings, with the warning text pulled from term descriptions.  
- Users can override admin settings by choosing their own tag warning preferences.  
- Warning can be applied at the post or block level, or using a shortcode with terms as parameters.  
- Multiple term descriptions are shown if more than one warning term is applied.  
- Supports tagging blog posts and media uploads using BuddyBoss front-end.

== Installation ==

1. Download the plugin as a `.zip` file or install it directly from the WordPress plugin repository.
2. Go to **Plugins > Add New** and click **Upload Plugin** (if using the `.zip`).
3. After installation, click **Activate Plugin**.
4. Configure warning terms by navigating to **Settings > Content Warning**.

== Usage ==

### **Admin Settings**  
1. Go to **Settings > Content Warning**.  
2. Select the terms that require a content warning.  
3. Add a description to each term to provide the warning text.

### **User Settings**  
1. Users can go to **Your Profile** to adjust their warning settings.  
2. They can disable warnings for certain terms set by the admin or add their own terms to trigger warnings.

### **Post term usage**
To apply a content warning to an entire post, apply a term that requires a content warning to the post. The content warning taxonomy will appear in the post editor screen, alongside tags, and are used in the same way.

### **Block Usage**
1. In the block editor, add the Content Warning block.
2. Select warning terms in the block settings.
3. Add your content, which will be blurred until the user agrees to view it.

### **Shortcode Usage**  
Use the `[content_warning]` shortcode to apply warnings within individual posts or pages. The slug of the term or terms should be entered into the shortcode separated by commas.  

**Example 1:** Single term  

`[content_warning tags="sensitive"]
This section discusses sensitive material.
[/content_warning]`

**Example 2:** Multiple terms  

`[content_warning tags="graphic,offensive"]
This section contains graphic language and offensive themes.
[/content_warning]`

== Frequently Asked Questions ==

**Q: What happens if multiple terms apply to a post?**  
A: All applicable tag descriptions are concatenated and displayed as warnings.  

**Q: Can users disable warnings for certain terms?**  
A: Yes, users can override the admin’s settings through their profile. They can disable certain warnings or add new tags that they want to be warned about.

**Q: Can you mix block and shortcode warnings on the same page or post?**  
A: Yes, you can.

== Screenshots ==

1. [Example of content warning]![screenshot-1](assets/screenshot-1.png)
2. [Block settings]![screenshot-2](assets/screenshot-2.png)
3. [Admin settings]![screenshot-3](assets/screenshot-3.png)
4. [User settings]![screenshot-4](assets/screenshot-4.png) 

== Changelog ==

### Version 1.1
- Moved from using post tags to a custom taxonomy for identifying terms that receive a warning.

### Version 1.0  
- Initial release.  
- Admin and user tag-based warning configurations.  
- Support for multiple tag descriptions in warnings.  
- Support for post, block, and shortcode-level warnings.

== Known Limitations ==

- Content is not blurred on category pages.

== Roadmap ==

- **Geolocation-based Warnings**: Modify content visibility based on the viewer’s location.  
- **Custom Styling Options**: Provide options to style blurred content and buttons using CSS.  
- **BuddyBoss Support**: Add user content warning settings module to BuddyBoss Account Settings screen.
- **Apply Shortcode from Editing Toolbar**: Simpler application of content warnings to text selections.

== License ==

This plugin is licensed under the GPLv2 or later. See the [license](https://www.gnu.org/licenses/gpl-2.0.html) for more details.
