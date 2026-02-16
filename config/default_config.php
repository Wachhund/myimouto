<?php
namespace MyImouto;

/**
 * Custom configuration should be set in the config.php file,
 * instead of directly modifying this file.
 */
abstract class DefaultConfig
{
    # The name of this booru.
    public $app_name    = 'my.imouto';

    # Host name. Must not include scheme (i.e. http(s)://) nor trailing slash.
    public $server_host = '127.0.0.1:3000';

    # This is the same as $server_host but includes scheme.
    public $url_base    = 'http://127.0.0.1:3000';

    # The version of this MyImouto
    public $version = '1.0.8';

    # This is a salt used to make dictionary attacks on account passwords harder.
    public $user_password_salt = 'choujin-steiner';

    # Set to true to allow new account signups.
    public $enable_signups = true;

    # Newly created users start at this level. Set this to 30 if you want everyone
    # to start out as a privileged member.
    public $starting_level = 30;

    # What method to use to store images.
    # local_flat: Store every image in one directory.
    # local_hierarchy: Store every image in a hierarchical directory, based on the post's MD5 hash. On some file systems this may be faster.
    # local_flat_with_amazon_s3_backup: Store every image in a flat directory, but also save to an Amazon S3 account for backup.
    # amazon_s3: Save files to an Amazon S3 account.
    # remote_hierarchy: Some images will be stored on separate image servers using a hierarchical directory.
    public $image_store = 'local_hierarchy';

    # Set to true to enable downloading whole pools as ZIPs.
    public $pool_zips = true;

    # Enables image samples for large images.
    public $image_samples = true;

    # The maximum dimensions and JPEG quality of sample images.    This is applied
    # before sample_max/sample_min below.    If sample_width is nil, neither of these
    # will be applied and only sample_min/sample_max below will determine the sample
    # size.
    public $sample_width = null;
    public $sample_height = 1000; # Set to null if you never want to scale an image to fit on the screen vertically
    public $sample_quality = 92;

    # The greater dimension of sample images will be clamped to sample_min, and the smaller
    # to sample_min.    2000x1400 will clamp a landscape image to 2000x1400, or a portrait
    # image to 1400x2000.
    public $sample_max = 1500;
    public $sample_min = 1200;

    # The maximum dimensions of inline images for the forums and wiki.
    public $inline_sample_width = 800;
    public $inline_sample_height = 600;

    # Resample the image only if the image is larger than sample_ratio * sample_dimensions.
    # This is ignored for PNGs, so a JPEG sample is always created.
    public $sample_ratio = 1;

    # A prefix to prepend to sample files
    public $sample_filename_prefix = '';

    # Enables creating JPEGs for PNGs.
    public $jpeg_enable = true;

    # Scale JPEGs to fit in these dimensions.
    public $jpeg_width = 3500;
    public $jpeg_height = 3500;

    # Resample the image only if the image is larger than jpeg_ratio * jpeg_dimensions.    If
    # not, PNGs can still have a JPEG generated, but no resampling will be done.
    #
    # Moebooru is getting confusing. For now, the max value will be used as JPEG quality.
    public $jpeg_ratio = 1.25;
    public $jpeg_quality = array('min' => 94, 'max' => 97, 'filesize' => 4194304 /*1024*1024*4*/);

    # If enabled, URLs will be of the form:
    # http://host/image/00112233445566778899aabbccddeeff/12345 tag tag2 tag3.jpg
    #
    # This allows images to be saved with a useful filename, and hides the MD5 hierarchy (if
    # any).    This does not break old links; links to the old URLs are still valid.    This
    # requires URL rewriting (not redirection!) in your webserver.
    public $use_pretty_image_urls = false;

    # If use_pretty_image_urls is true, sets a prefix to prepend to all filenames.    This
    # is only present in the generated URL, and is useful to allow your downloaded files
    # to be distinguished from other sites; for example, "moe 12345 tags.jpg" vs.
    # "kc 54321 tags.jpg".
    public $download_filename_prefix = "myimouto";

    # Files over this size will always generate a sample, even if already within
    # the above dimensions.
    public $sample_always_generate_size = 524288; // 512*1024

    # After a post receives this many comments, new comments will no longer bump the post in comment/index.
    public $comment_threshold = 9999;

    # Members cannot post more than X posts in a day.
    public $member_post_limit = 16;

    # This sets the minimum and maximum value a user can record as a vote.
    public $vote_record_min = 0;
    public $vote_record_max = 3;

    # This allows posts to have parent-child relationships. However, this requires manually updating the post counts stored in table_data by periodically running the script/maintenance script.
    public $enable_parent_posts = true;

    # Show only the first page of post/index to visitors.
    public $show_only_first_page = false;

    # Defines the various user levels.
    public $user_levels = [
        "Unactivated" => 0,
        "Blocked"     => 10,
        "Member"      => 20,
        "Privileged"  => 30,
        "Contributor" => 33,
        "Janitor"     => 35,
        "Mod"         => 40,
        "Admin"       => 50
    ];

    # Defines the various tag types. You can also define shortcuts.
    public $tag_types = [
        "General"   => 0,
        "general"   => 0,
        "Artist"    => 1,
        "artist"    => 1,
        "art"       => 1,
        "Copyright" => 3,
        "copyright" => 3,
        "copy"      => 3,
        "Character" => 4,
        "character" => 4,
        "char"      => 4,
        "Circle"    => 5,
        "circle"    => 5,
        "cir"       => 5,
        "Faults"    => 6,
        "faults"    => 6,
        "fault"     => 6,
        "flt"       => 6
    ];

    # Tag type ordering in various locations.
    public $tag_order = [
        'circle',
        'artist',
        'copyright',
        'character',
        'general'
    ];

    # Tag type IDs to not list in recent tag summaries, such as on the side of post/index:
    public $exclude_from_tag_sidebar = array(0, 6);

    # Determine who can see a post.
    public function can_see_post(\User $user, \Post $post)
    {
        # By default, no posts are hidden.
        return true;

        # Some examples:
        #
        # Hide post if user isn't privileged and post is not safe:
        # if ($post->rating == 'e' && $user->is_privileged_or_higher()) return true;
        #
        # Hide post if user isn't a mod and post has the loli tag:
        # if ($post->has_tag('loli') && $user->is_mod_or_higher()) return true;
    }

    # Determines who can see ads.
    public function can_see_ads($user)
    {
        return $user->is_member_or_lower();
    }

    # Defines the default blacklists for new users.
    public $default_blacklists = array (
        "rating:q",
        "rating:e"
    );

    # Enable the artists interface.
    public $enable_artists = true;

    # Users cannot search for more than X regular tags at a time.
    public $tag_query_limit = 6;

    # Set this to true to hand off time consuming tasks (downloading files, resizing images, any sort of heavy calculation) to a separate process.
    # @see $active_job_tasks
    # @see is_job_task_active()
    public $enable_asynchronous_tasks = false;

    public $avatar_max_width = 125;
    public $avatar_max_height = 125;

    # The number of posts a privileged_or_lower can have pending at one time.    Any
    # further posts will be rejected.
    public $max_pending_images = null;

    # If set, posts by privileged_or_lower accounts below this size will be set to
    # pending.
    public $min_mpixels = null;

    # If true, pending posts act like hidden posts: they're hidden from the index unless
    # pending:all is used, and posts are bumped to the front of the index when they're
    # approved.
    public $hide_pending_posts = true;

    # The image service name of this host, if any.
    public $local_image_service = "";

    # List of image services available for similar image searching.
    public $image_service_list = [
        "danbooru.donmai.us" => "http://iqdb.org/index.xml",
        "yande.re"           => "http://iqdb.org/index.xml",
        "konachan.com"       => "http://iqdb.org/index.xml"
    ];

    public $dupe_check_on_upload = false;

    # Members cannot post more than X comments in an hour.
    public $member_comment_limit = 20;

    # (Next 2 arrays will be filled when including config/languages.php)
    public $language_names = [];

    # Languages that we're aware of.    This is what we show in "Secondary languages", to let users
    # select which languages they understand and that shouldn't be translated.
    public $known_languages = [];

    # Languages that we support translating to.    We'll translate each comment into all of these
    # languages.    Set this to array() to disable translation.
    public $translate_languages = []; // array('en', 'ja', 'zh-CN', 'zh-TW', 'es'):

    public $available_locales = ['de', 'en', 'es', 'ja', 'ru', 'zh_CN'];

    # The default name to use for anyone who isn't logged in.
    public $default_guest_name = "Anonymous";

    public $admin_contact = 'admin@myimouto';

    # Background color when resizing transparent PNG/GIF images.
    # Using RGB values.
    # Default: [126, 126, 126] (gray)
    public $bgcolor = [126, 126, 126];

    # Default language.
    public $default_locale = 'en';

    # Use this config to enable Google Analytics. Fill in the GA Tracking ID (like 'UA-XXXXX-X')
    public $ga_tracking_id = '';

    # Max number of posts to cache
    public $tag_subscription_post_limit = 200;

    # Max number of fav tags per user
    public $max_tag_subscriptions = 5;

    /**
     * *******************************
     * MyImouto-specific configuration
     * *******************************
     */

    # Default limit for /post
    public $post_index_default_limit = 16;

    # Default limit for /pool
    public $pool_index_default_limit = 20;

    # For /post tag left-sidebar, show tags of posts that were posted N days ago.
    # This value will be passed to strtotime(). Check out http://php.net/manual/en/function.strtotime.php
    # for more info.
    # The leading minus sign (-) will be added automatically, therefore must be omitted.
    public $post_index_tags_limit = '1 day';

    # Default rating for upload (e, q or s).
    public $default_rating_upload = 'q';

    # Default rating for import (e, q or s).
    public $default_rating_import = 'q';

    # Automatically add "gif" tag to GIF files.
    public $add_gif_tag_to_gif = true;

    # Automatically add "flash" tag to SWF files.
    public $add_flash_tag_to_swf = true;

    # Enables the E/R hotkeys to jump to the edit/reply forms respectevely in post#show.
    public $post_show_hotkeys = true;

    # Max number of dmails users can send in one hour.
    public $max_dmails_per_hour = 10;

    # Only Job tasks listed here will be active.
    # @see $enable_asynchronous_tasks
    # @see is_job_task_active()
    public $active_job_tasks = [
        'periodic_maintenance',
        'external_data_search',
        'upload_batch_posts',
        'approve_tag_implication',
        'approve_tag_alias',
        'calculate_tag_subscriptions'
    ];

    # Javascripts assets manifest files.
    public $asset_javascripts = [
        'application',
        'moe-legacy/application'
    ];

    # Stylesheets assets manifest files.
    public $asset_stylesheets = [
        'application'
    ];

    # Adds delete option to Post Mode menu in post#index, only available for admins.
    # Be careful with this.
    public $delete_post_mode = false;

    # When deleting a post, it will be deleted completely at once.
    # Be careful with this.
    public $delete_posts_permanently = false;

    # Enables post#search_external_data, available for any user.
    public $enable_find_external_data = true;

    # Enables manual tag deletion.
    public $enable_tag_deletion = true;

    # Enables manual tax fix count.
    public $enable_tag_fix_count = true;

    # Show homepage or redirect to /post otherwise.
    public $skip_homepage = false;

    # Show moe imoutos (post count) in homepage.
    public $show_homepage_imoutos = true;

    # Creates a fake sample_url for posts without a sample, so they can be zoomed-in in browse mode.
    # This is specifically useful if you're not creating image samples.
    public $fake_sample_url = true;

    # Parse moe imouto filenames upon post creation
    # These only work if filename is like "moe|yande.re 123 tag_1 tag_2" by default.
    # You can modify how the filenames are parsed in the
    # app/models/post/filename_parsing_methods.php file, the
    # _parse_filename_tags and _parse_filename_source methods.

    # Take tags from filename.
    public $tags_from_filename = true;

    # Automatically create source for images.
    public $source_from_filename = true;

    # Enable resizing image in post#show by double-clicking on it.
    public $dblclick_resize_image = true;

    # Enable news bar on the top of the page.
    public $enable_news_ticker = true;

    # Menu wiki link parameters.
    # By default it links to "help:home" wiki, but not all boorus will have a wiki called
    # like that. Set the value to false or null if you want to link to the wiki index instead.
    public $menu_wiki_link = ['title' => "help:home"];

    # By default, filenames in pool zips are named after the pool order of the post.
    # The following option will show an option in pool#show that will let users
    # keep pretty filenames instead (with id and tags, like in post#show).
    public $allow_pool_zip_pretty_filenames = true;

    # Makes the results in post#similar open in a new window.
    public $similar_image_results_on_new_window = true;

    # When clicking "Add translation" in post#show, instead of creating a note, a notice will
    # show with info on how to create a note using the new functionality.
    # Setting this to false will cause the link to create a note like before, but the new
    # functionality will stay.
    public $disable_old_note_creation = true;

    # Only users with level equal or higher than this can see posts.
    # Unless they have higher level, users won't be able to view posts or access the Post Browser,
    # download pool zips and also direct-links in post lists (post#index, pool#show) will be disabled for them.
    # It's recommended to combine this with anti-directlink rules in the .htaccess.
    public $user_min_level_can_see_posts = 0;

    # Enables tag autocomplete in various input fields.
    public $enable_tag_completion = true;

    # Enables tag autocomplete in the home page.
    # Requires $enable_tag_completion.
    public $tag_completion_in_homepage = false;

    # Configuration for External Data Search job task.
    # Only the needed values to be changed can be copied over the
    # custom config class.
    public $external_data_search_config = [
        # List of server names to search data in.
        # The server names listed here are just a reference to the list in the
        # image_service_list option; i.e., the names listed here must also be
        # listed in image_service_list.
        'servers' => [
            "danbooru.donmai.us"
        ],

        # Interval between requests, in seconds.
        'interval' => 3,

        # True to grab post source from the first result with source.
        # Otherwise, source is not touched.
        'source' => true,

        # True to merge found tags with current tags.
        # False to replace current tags with found ones.
        'merge_tags' => true,

        # Max post updates per job call.
        # 0 = no limit = find data non-stop for all posts till end or error.
        'limit' => 100,

        # Automatically sets rating from the first similar post with rating (usually
        # the best match).
        'set_rating' => false,

        # Exclude these tags from result tags.
        'exclude_tags' => [],

        # Image similarity threshold. Images with lower similarity ratio
        # will be ignored.
        # Set to 0 for automatic threshold.
        'similarity' => 90
    ];

    /**
     * Active advertisements spots.
     * Remove one of them from the list and the ads in that
     * page/place won't show.
     * The ones commented are new, extra spots.
     */
    public $active_ad_spots = [
        'post#index-sidebar',
        'post#show-sidebar',
        'post#show-top',
        'post#index-top',
        // 'post#index-bottom',
        // 'post#show-bottom'
    ];

    # Timeout for Danbooru::http_get_streaming() (used by batch upload, similar images, etc.).
    # If this limit is reached, the file that's being downloaded will be invalid.
    # Set to 0 to wait indefinitely.
    public $http_streaming_timeout = 10;

    /**
     * Don't process tag subscriptions again within this time.
     * Set to null to process tag subscriptions asap.
     *
     * @param string
     */
    public $tag_subscription_delay = '360 minutes';

    public function __get($prop)
    {
        return null;
    }

    # Just enabling tasks won't assure a certain task is active.
    # This will tell us if job tasks are enabled and if a specific task is active.
    public function is_job_task_active($name)
    {
        return $this->enable_asynchronous_tasks && in_array($name, $this->active_job_tasks);
    }

    /**
     * Does a couple of checks:
     * - If $user can see ads.
     * - If $spot is active.
     *
     * @return bool
     */
    public function can_show_ad($spot, $user)
    {
        return in_array($spot, $this->active_ad_spots) && $this->can_see_ads($user);
    }

    /**
     * Parses image filename into tags.
     * Must return an array with tags or an empty value.
     */
    public function filename_to_tags($filename)
    {
        if (preg_match("/^(?:yande\.re|moe) \d+ (.*)$/", $filename, $m)) {
            return array_filter(array_unique(explode(' ', $m[1])));
        }
    }

    /**
     * Parses image filename into source.
     * Must return a string or an empty value.
     */
    public function filename_to_source($filename)
    {
        if (preg_match("/^(?:moe|yande\.re) (\d+) /", $filename, $m)) {
            return 'https://yande.re/post/show/'.$m[1];
        }
    }
}
