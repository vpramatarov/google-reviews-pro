=== Google Reviews Pro ===
Contributors: vpramatarov
Tags: google reviews, places api, serpapi, schema.org, seo reviews, business reviews, ajax, slider, badge, rank math
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.3
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Google Reviews Pro is an enterprise-grade WordPress solution for integrating Google Business reviews. Built with a "Sync-to-Database" architecture, it ensures maximum performance, reliability, and SEO benefits by storing reviews locally.

**🚀 New in v1.1.0:**
* **4 Layout Modes:** Grid, List, Slider (Carousel), and Floating Badge.
* **Multi-Location Support:** Display reviews for different branches using shortcode attributes.
* **Reputation Management:** Hide specific reviews and filter by minimum rating (e.g., "4 Stars & Up").
* **Smart Finder:** Auto-detect Business IDs and coordinates.
* **Performance:** "Load More" AJAX button for faster initial page loads.

= Key Features =
* **Hybrid Data Source:** Choose between Google Places API, SerpApi (Scraper), or Manual Entry.
* **Visual Layouts:** Choose between **Grid**, **List**, **Slider**, or a **Floating Trust Badge**.
* **SEO Integrator:** Auto-syncs business data from **Rank Math**, **Yoast**, **All in One SEO**, **SEOPress**, and **The SEO Framework**.
* **Local Storage:** Reviews are stored as Custom Post Types for instant loading and zero API dependency on the frontend.
* **Local Avatar Caching:** Auto-downloads user profile photos to your Media Library (fixes expired Google links).
* **Advanced Filtering:** Set a minimum star rating (e.g., 4+) and sort by Date, Rating, or Random.
* **Moderation:** Manually hide specific negative reviews from the admin panel without deleting them.
* **Email Alerts:** Receive notifications when new reviews are fetched via cron.
* **Uninstall Cleanup:** Optional setting to wipe all data upon plugin deletion.
* **PHP 8.3 Optimized:** Built with strict typing and readonly classes for stability.

== Installation ==

1. Upload the `google-reviews-pro` folder to the `/wp-content/plugins/` directory.
2. Ensure your server is running **PHP 8.3** or higher.
3. Run `composer install --no-dev --optimize-autoloader` in the plugin root.
4. Activate the plugin through the 'Plugins' menu in WordPress.
5. Navigate to **Settings -> Google Reviews**.
6. Configure your API key and use the **Smart Finder**.
7. Place the shortcode `[google_reviews]` on any page.

== Configuration & Usage ==

= 1. Basic Setup =
1. Go to Settings -> Google Reviews.
2. Enter your API Key (Google or SerpApi) and **Save**.
3. Use the **"Find Your Business"** tool to auto-fill your Place ID/Data ID.
4. Click **"Sync Reviews Now"** to fetch your data.

= 2. Multi-Location Strategy (Chains & Franchises) =
The plugin allows you to store and display reviews for multiple locations (e.g., "Downtown Branch" and "Uptown Branch").

**How to fetch data for multiple locations:**
1. Enter the **Place ID** for *Location A* in settings -> Click **Save** -> Click **Sync Reviews**.
2. Change the **Place ID** to *Location B* in settings -> Click **Save** -> Click **Sync Reviews**.

**How to display them:**
Use the `place_id` attribute in the shortcode to filter reviews:

* **Location A:** `[google_reviews place_id="ChIJ_Location_A_ID"]`
* **Location B:** `[google_reviews place_id="ChIJ_Location_B_ID"]`

*Tip:* Check the **"Multi-Location Reference"** table in the settings page to see all stored Place IDs and copy their shortcodes.

= 3. Layouts & Styling =
You can customize the look in the **Styling & Layout** section:
* **Layout Style:** Choose between Grid, List, Slider, or Badge.
* **Colors:** Customize Text, Background, Accents, and Buttons.
* **Load More:** In Grid/List view, a "Load More" button appears automatically if there are more reviews than the initial limit (6).

== Frequently Asked Questions ==

= How do I hide a specific bad review? =
1. Go to **Reviews** in your WordPress Admin menu.
2. Click **Edit** on the review you want to hide.
3. In the right sidebar, look for the **Moderation** box.
4. Check **"Hide this review from frontend"** and Update.

= Can I show only 5-star reviews? =
Yes. Go to **Settings -> Google Reviews**, scroll to **Filtering & Moderation**, and set **Minimum Rating** to "5 Stars Only".

= Does it support my SEO plugin? =
Yes! We support bi-directional sync (read-only) for:
* Rank Math SEO
* Yoast SEO
* All in One SEO (AIOSEO)
* SEOPress
* The SEO Framework
  If detected, we automatically pull your Business Name, Address, and Phone from the SEO plugin settings to prevent data conflicts.

= What is "Uninstall Cleanup"? =
In the **Advanced Settings**, there is a "Uninstall Cleanup" checkbox. If checked, when you delete the plugin from WordPress, it will **permanently delete** all imported reviews, downloaded images, and settings from your database. Leave unchecked if you plan to reinstall later.

== API Keys ==

* **Google Places API:** [Get Key](https://console.cloud.google.com/google/maps-apis/credentials) (Official, Real-time).
* **SerpApi:** [Get Key](https://serpapi.com/) (Scraper, supports deep pagination).

== Changelog ==

= 1.0.0 =
* **Feature:** 4 Layout Modes (Grid, List, Slider, Badge).
* **Feature:** Multi-Location support via shortcode `[google_reviews place_id="..."]`.
* **Feature:** "Stored Locations" reference table in settings.
* **Feature:** "Load More" button with AJAX loading.
* **Feature:** Advanced Filtering (Min Rating, Sort Order).
* **Feature:** Manual Moderation (Hide individual reviews).
* **Feature:** Email Notifications for new reviews.
* **Feature:** Uninstall Cleanup option.
* **Feature:** Extended SEO Plugin Support (Rank Math SEO, Yoast SEO, AIOSEO, SEOPress, TSF).
* **Feature:** Admin columns now show Reviewer Avatar and Rating.
* **Feature:** Reviews use WordPress native "Featured Image" for avatars.
* **Feature:** "Smart Finder" logic for finding Place IDs.