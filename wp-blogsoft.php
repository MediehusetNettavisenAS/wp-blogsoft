<?php
header('Content-Type: text/html; charset=utf-8');
/*
 * Plugin Name: Blogsoft
 * Plugin URI: http://blogsoft.no/wp-blogsoft
 * Description: Publish your wordpress atricles in your blogsoft blog.
 * Version: 1.0.1
 * Author: BlogSoft
 * Author URI: http://blogsoft.no
 * Contributors: Supankar Banik (github.com/supankar)
 * License: GPL2+
 * Text Domain: wp-blogsoft
 * Domain Path: /lang
 * Copyright 2015 blogsoft.no (email: info@blogsoft.no)
 */
require_once('modules/oauth-blogsoft.php');

class WP_Blogsoft
{
    public $pluginversion = '1.0.1';
    public $pluginname = 'Blogsoft';
    public $hook = 'wp-blogsoft'; // $this->hook
    public $accesslvl = 'manage_options';
    public $default_settings = array(
        'appID' => '',
        'appSecret' => '',
        'access_token' => '',
        'bloggno_blog_id' => '',
        'bloggno_blog_url' => '',
        'bloggno_category_id' => '',
        'bloggno_category_name' => '',
        'blogsoft_seo' => '',
        'bloggno_rules' => '',
    );

    function __construct()
    {
        load_plugin_textdomain($this->hook, false, dirname(plugin_basename(__FILE__)) . '/language'); // Load plugin text domain
        add_action('admin_print_styles', array($this, 'register_admin_styles')); // Register admin styles
        add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts')); // Register admin scripts
        add_action('admin_menu', array($this, 'action_menu_pages')); // Registers all WordPress admin menu items
        

        // article publish actions
        add_action('publish_post', array($this, 'blogsoft_article_published'));
        add_action("publish_to_draft", array($this, 'blogsoft_article_draft'));
        add_action("publish_to_pending", array($this, 'blogsoft_article_pending'));
        add_action("trash_post", array($this, 'blogsoft_article_delete'));
        add_action("untrash_post", array($this, 'blogsoft_article_published'));

        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        //add_action('save_post', array($this, 'save'));

        // front-end actions
        add_action('wp_head', array($this, 'add_canonical_link'), 1);

        add_action('admin_notices', array($this, 'blogsoft_post_admin_notices'));


        global $blogSoftOauth;
        $blogSoftOauth = new BlogsoftOAuth;

        add_filter('init', array($this, 'blogsoft_init'));
        add_filter('http_request_timeout', array($this, 'blogsoft_publish_timeout_time'));
    }

    //end construct


    public function register_session(){
        if( !session_id() )
            session_start();
    }



    public function blogsoft_post_admin_notices() {
        global $current_screen;

        if( $current_screen->post_type =='post') {
            $settings = self::blogsoft_get_settings();
            if (isset($settings['blogsoft_invalid_token']) && $settings['blogsoft_invalid_token'] == 1) {
                echo '<div class="error"><p><img src="'. plugins_url($this->hook.'/images/logo.png', dirname(__FILE__)). '" title="blogsoft" style="vertical-align: middle; width:17px;" />&nbsp;<strong>'.__('Authentication failed!. You have to authorize your blogsoft account again to publish article in your blogg.no blog. Please', $this->hook).' <a href='. admin_url('admin.php?page=wp-blogsoft') .'> '.__('click', $this->hook).'</a> '.__('here', $this->hook).' </strong></p></div>';
            }

            if(isset($_SESSION['blogsoftMsg']) && sizeof($_SESSION['blogsoftMsg'])) {
                foreach($_SESSION['blogsoftMsg'] as $blogsoftMsg){
                    echo '<div id="notice" class="'.$blogsoftMsg['status'].'"><p>'. $blogsoftMsg['msg'] .'</p></div>';
                }
            }
        }
        unset($_SESSION['blogsoftMsg']);
    }


    /*
     * Registers and enqueues admin-specific styles.
     */
    public function register_admin_styles()
    {
        if (isset($_GET['page']) && strpos($_GET['page'], $this->hook) !== false) {
            wp_enqueue_style('blogsoft-core', plugins_url('css/admin.css', __FILE__), array(), $this->pluginversion);
        }
    }

    /**
     * Adds the meta box container.
     */
    public function add_meta_box( $post_type ) {
        if ($post_type = 'post') {

            $settings = self::blogsoft_get_settings();

            if (!isset($settings['blogsoft_invalid_token']) || $settings['blogsoft_invalid_token'] != 1) {
                add_meta_box(
                    'blogsoft_meta_box'
                    ,__( '<img src="'. plugins_url($this->hook.'/images/logo.png', dirname(__FILE__)). '" title="blogsoft" style="vertical-align: middle; width:17px;" />&nbsp;'.__('Share your article in Blogg.no', $this->hook), 'blogsoft_textdomain' )
                    ,array( $this, 'render_meta_box_content' )
                    ,'post'
                    ,'side'
                    ,'high'
                );
            }
        }
    }


    /**
    * Save the meta when the post is saved.
    *
    * @param int $post_id The ID of the post being saved.
    */
    public function checkPublishStatus( $post_id ) {
        /*
         * We need to verify this came from the our screen and with proper authorization,
         * because save_post can be triggered at other times.
         */

        // Check if our nonce is set.
        if ( ! isset( $_POST['blogsoft_box_nonce'] ) )
            return $post_id;

        $nonce = $_POST['blogsoft_box_nonce'];

        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'blogsoft_box' ) )
            return $post_id;

        // If this is an autosave, our form has not been submitted,
        //     so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return $post_id;

        // Check the user's permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) )
            return $post_id;


        $publishInBlogSoft = 'off';
        if(isset($_POST['blogsoft_publish_status'])) {
            $publishInBlogSoft = 'on';
        }

        // Update the meta field.
        update_post_meta( $post_id, 'blogsoft_publish_status', $publishInBlogSoft );
    }


    /**
     * Render Meta Box content.
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box_content( $post ) {

        global $current_screen;

        // Add an nonce field so we can check for it later.
        wp_nonce_field( 'blogsoft_box', 'blogsoft_box_nonce' );

        $value = get_post_meta( $post->ID, 'blogsoft_publish_status', true );
        if( $current_screen->post_type =='post' && $current_screen->action == 'add') {
            $value =  'on' ;
        }

        echo '<input type="checkbox" id="blogsoft_publish_status" name="blogsoft_publish_status" value="1"'.(($value == 'on')? 'checked="checked"' : '').' />'. __('Publish in blogsoft', $this->hook);

        $blogsoftArticleURL = get_post_meta($post->ID, 'blogsoft_article_url', true);
        if (!empty($blogsoftArticleURL) && $value == 'on') {
            echo '<br /><a href="' . $blogsoftArticleURL . '" target="_blank" >'. __('View Article', $this->hook).' </a>';
        }
    }





    /*
     * Registers and enqueues admin-specific JavaScript.
     */
    public function register_admin_scripts()
    {
        if (isset($_GET['page']) && strpos($_GET['page'], $this->hook) !== false) {
            wp_enqueue_script('dashboard');
            wp_enqueue_script('postbox');
            wp_enqueue_script('admin-core', plugins_url('js/admin.js', __FILE__), array(), $this->pluginversion);
        }
    }

    /*
     * Registers all WordPress admin menu items.
     */
    function action_menu_pages()
    {
        add_menu_page(
            __($this->pluginname, $this->hook) . ' - ' . __('Basic Settings and Connect', $this->hook),
            __($this->pluginname, $this->hook),
            $this->accesslvl,
            $this->hook,
            array($this, 'blogsoft_options_panel'),
            plugins_url('images/logo.png', __FILE__)
        );
    }

    function blogsoft_options_panel()
    {
        include(dirname(__FILE__) . '/views/basic_setting.php');
    }

    /*
     * All settings
     */
    function blogsoft_get_settings()
    {
        $settings = $this->default_settings;
        $wordpress_settings = get_option('blogsoft_settings');
        if ($wordpress_settings) {
            foreach ($wordpress_settings as $key => $value) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }


    /*
     * Save settings...
     */
    function blogsoft_update_post_settings()
    {
        if (is_admin()) {
            $settings = self::blogsoft_get_settings();
            if (isset($_POST['blogsoft_update_settings'])) {

                if (isset($_POST['publish_in_blogsoft'])) {
                    $settings['publish_in_blogsoft'] = true;
                } else {
                    $settings['publish_in_blogsoft'] = false;
                }

                if (isset($_POST['blogsoft_seo'])) {
                    $settings['blogsoft_seo'] = true;
                } else {
                    $settings['blogsoft_seo'] = false;
                }

                if (isset($_POST['bloggno_category_id'])) {
                    $settings['bloggno_category_id'] = $_POST['bloggno_category_id'];
                }


                $blogRules = array();
                if(isset($_POST['wp_cat']) && isset($_POST['bs_cat'])) {
                   foreach($_POST['wp_cat'] as $key => $value) {
                       $blogRules[$value] = $_POST['bs_cat'][$key];
                   }
                }

                $settings['bloggno_rules'] = serialize($blogRules);

                self::blogsoft_save_settings($settings);
            } elseif (isset($_POST['reset'])) {
                update_option('blogsoft_settings', false);
            }
        }
    }


    /*
     * init()
     */
    function blogsoft_init()
    {
        $this->register_session();

        global $blogSoftOauth;
        self::blogsoft_update_post_settings();

        if (isset($_POST['blogsoft_authorize'])) {
            $appID = trim($_POST['appID']);
            $appSecret = trim($_POST['appSecret']);

            $errMsg = array();
            if(empty($appID)) {
                $errMsg[] = __('App ID is missing', $this->hook);
            }

            if(empty($appSecret)) {
                $errMsg[] = __('App Secret is missing', $this->hook);
            }

            $settings = self::blogsoft_get_settings();


            $settings['appID'] = $appID;
            $settings['appSecret'] = $appSecret;
            self::blogsoft_save_settings($settings);

            $blogSoftOauth->set_oauth_tokens($appID,$appSecret);
            $oAuthURL = $blogSoftOauth->get_authorize_url();
            header('Location: ' . $oAuthURL);
            exit();
        }


        if (isset($_GET['code'])) {
            $settings = self::blogsoft_get_settings();
            $result = $blogSoftOauth->get_access_token($_GET['code']);
            //print_r($result);
            //exit();
            $result = json_decode($result, true);
            if (sizeof($result)) {
                $settings['access_token'] = $result['access_token'];
                $settings['blogsoft_invalid_token'] = 0;

                $params = array();
                $params['method'] = 'getBlogs';
                $params['access_token'] = $settings['access_token'];


                $blogs = $blogSoftOauth->apiCall($params);

                if (isset($blogs['entries']) && $blogs['entries'] > 0) {

                    $settings['bloggno_blog_id'] = $blogs['data'][0]['id'];
                    $settings['bloggno_blog_url'] = $blogs['data'][0]['url'];
                }

                $params['method'] = 'getCategories';
                $params['access_token'] = $settings['access_token'];
                $params['blogurl'] = $settings['bloggno_blog_url'];



                self::blogsoft_save_settings($settings);

                $blogsoftCategories = self::blogsoft_categories();
                if (sizeof($blogsoftCategories) > 0) {
                    foreach($blogsoftCategories as $key => $category) {
                        $settings['bloggno_category_id'] = $key;
                        $settings['bloggno_category_name'] = $category;
                        break;
                    }
                }

                self::blogsoft_save_settings($settings);
                header('Location: ' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=' . $this->hook);
                die;
            }
        } else if (isset($_GET['blogsoft']) && $_GET['blogsoft'] == 'deauthorize') {
            $this->blogsoft_remove_settings();
            global $wpdb;
            $wpdb->query( $wpdb->prepare( 'DELETE FROM wp_postmeta WHERE meta_key LIKE "%s"', 'blogsoft_%'));
            header('Location: ' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=' . $this->hook);
            die();
        }
    }

    /*
     * abc
     */
    function blogsoft_save_settings($settings)
    {
        update_option('blogsoft_settings', $settings);
    }

    /*
     * Remove settings
     */
    function blogsoft_remove_settings()
    {
        delete_option('blogsoft_settings');
    }


    /*
     * remove_settings_for_invalid_token
     */
    function blogsoft_remove_settings_for_invalid_token($blogsoft_response)
    {
        if(isset($blogsoft_response['error_code']) && $blogsoft_response['error_code'] == 1) {
            $this->blogsoft_remove_settings();

            $settings = self::blogsoft_get_settings();
            $settings['blogsoft_invalid_token'] = 1;
            self::blogsoft_save_settings($settings);

        } elseif(isset($blogsoft_response['error'])) {
            $_SESSION['blogsoftMsg'][] = array('msg' => '<img src="'. plugins_url($this->hook.'/images/logo.png', dirname(__FILE__)). '" title="blogsoft" style="vertical-align: middle; width:17px;" />&nbsp;'. $blogsoft_response['error'], 'status' => 'error');
        }
    }

    /*
     * Authorize URL
     */
    function blogsoft_get_auth_url()
    {
        global $blogSoftOauth;
        return $blogSoftOauth->get_authorize_url();
    }


    /*
     * Publish article in blogsoft
     */
    function blogsoft_article_published($post_id, $post_status = 'publish')
    {
        $this->checkPublishStatus($post_id);

        $settings = self::blogsoft_get_settings();
        if (isset($settings['publish_in_blogsoft']) && $settings['publish_in_blogsoft'] && isset($settings['access_token'])) {

            $publishInBlogSoft = get_post_meta( $post_id, 'blogsoft_publish_status', true);
            if($publishInBlogSoft == 'off') {
                $this->blogsoft_article_delete($post_id);
                return;
            }

            $blognoRules = unserialize($settings['bloggno_rules']);
            if(sizeof($blognoRules) || $settings['bloggno_category_id'] > 0) {

                $blogsoftCategory = $settings['bloggno_category_id'];
                $postCategories = get_the_category($post_id);
                if(sizeof($postCategories)) {
                    foreach($postCategories as $postCategory) {
                        if(isset($blognoRules[$postCategory->cat_ID])) {
                            $blogsoftCategory = $blognoRules[$postCategory->cat_ID];
                            break;
                        }
                    }
                }

                if($blogsoftCategory > 0) {
                    $wp_post = get_post($post_id);

                    $params = array();
                    $params['method'] = 'createOrUpdatePost';
                    $params['publish_status'] = $post_status;
                    $params['blogurl'] = $settings['bloggno_blog_url'];
                    $params['access_token'] = $settings['access_token'];
                    $params['title'] = $wp_post->post_title;
                    $params['post_created'] = get_gmt_from_date($wp_post->post_date);
                    $params['postTypeId'] = 11;
                    $params['external_blog_url'] = get_site_url();

                    $content = apply_filters('the_content', $wp_post->post_content);
                    //$params['body'] = mb_detect_encoding($string, "UTF-8", true)($content);
                    $params['body'] = $this->santizeChar($content);
                    //$params['body'] = $content;

                    $params['categoryid'] = $blogsoftCategory;

                    if(!$settings['blogsoft_seo']) {
                        $params['canonicalLink'] = $wp_post->guid;
                    }

                    $blogsoftArticleID = get_post_meta($post_id, 'blogsoft_article_id', true);
                    if ($blogsoftArticleID > 0) {
                        $params['id'] = $blogsoftArticleID;
                    }

                    global $blogSoftOauth;
                    $article_publish = $blogSoftOauth->apiCall($params);

                    $this->blogsoft_remove_settings_for_invalid_token($article_publish);

                    if (isset($article_publish['id']) && $article_publish['id'] > 0) {
                        update_post_meta($post_id, 'blogsoft_article_id', $article_publish['id']);
                        update_post_meta($post_id, 'blogsoft_article_url', $article_publish['url']);
                        update_post_meta($post_id, 'blogsoft_seo', $settings['blogsoft_seo']);

                        $msg = __('Successfully published in blogsoft.', $this->hook).' <a href="'.$article_publish['url']. '" target="_blank">'.__('View post', $this->hook).'</a>';
                        if($post_status == 'draft') {
                            $msg = __('Successfully drafted in blogsoft.', $this->hook);
                        }

                        $_SESSION['blogsoftMsg'][] = array('msg' => '<img src="'. plugins_url($this->hook.'/images/logo.png', dirname(__FILE__)). '" title="blogsoft" style="vertical-align: middle; width:17px;" />&nbsp;'. $msg, 'status' => 'updated');
                    }
                }

            }
        }
    }

    /*
     *  draft article from blogsoft
     */
    function blogsoft_article_draft($post)
    {
        $postID = $post->ID;
        $this->blogsoft_article_published($postID, 'draft');
    }


    /*
     *  Delete article from blogsoft
     */
    function blogsoft_article_pending($post)
    {
        $postID = $post->ID;
        $this->blogsoft_article_delete($postID);
    }

    /*
     *  Delete article from blogsoft
     */
    function blogsoft_article_delete($post_id)
    {
        $blogsoftArticleID = get_post_meta($post_id, 'blogsoft_article_id', true);
        if ($blogsoftArticleID > 0) {

            $settings = self::blogsoft_get_settings();

            if (isset($settings['publish_in_blogsoft']) && $settings['publish_in_blogsoft'] && isset($settings['access_token'])) {
                $params = array();
                $params['blogurl'] = $settings['bloggno_blog_url'];
                $params['access_token'] = $settings['access_token'];
                $params['method'] = 'deletePost';
                $params['id'] = $blogsoftArticleID;

                global $blogSoftOauth;
                $blogsoftArticle =  $blogSoftOauth->apiCall($params);

                $this->blogsoft_remove_settings_for_invalid_token($blogsoftArticle);

                if (isset($blogsoftArticle['id']) && $blogsoftArticle['id'] > 0) {
                    global $wpdb;
                    $wpdb->query( $wpdb->prepare( 'DELETE FROM wp_postmeta WHERE post_id = %d AND meta_key LIKE "%s"', $post_id, 'blogsoft_%'));
                    $_SESSION['blogsoftMsg'][] = array('msg' => '<img src="'. plugins_url($this->hook.'/images/logo.png', dirname(__FILE__)). '" title="blogsoft" style="vertical-align: middle; width:17px;" />&nbsp;'.__('Successfully removed from blogsoft', $this->hook), 'status' => 'updated');
                    return;
                }
            }
        }
    }

    /*
     * blogsoft Categories
     */
    function blogsoft_categories()
    {
        $blogsoftCategories = array();
        $settings = self::blogsoft_get_settings();
        if (isset($settings['access_token']) && !empty($settings['access_token'])) {


            $params = array();
            $params['method'] = 'getCategories';
            $params['access_token'] = $settings['access_token'];
            $params['blogurl'] = $settings['bloggno_blog_url'];

            global $blogSoftOauth;
            $apiResponse = $blogSoftOauth->apiCall($params);

            $this->blogsoft_remove_settings_for_invalid_token($apiResponse);

            if (isset($apiResponse['entries']) && $apiResponse['entries'] > 0) {
                $categories = $apiResponse['data'];
                foreach($categories as $category) {
                    $blogsoftCategories[$category['id']] = $category['name'];
                }
            }
        }

        return $blogsoftCategories;
    }

    /*
     *  Adding canonical link in wordpress article
     */
    function add_canonical_link($post_id)
    {
        if ( !is_single())
            return;

        $blogsoftSEO = get_post_meta(get_the_ID(), 'blogsoft_seo', true);
        if($blogsoftSEO) {
            $blogsoftArticleURL = get_post_meta(get_the_ID(), 'blogsoft_article_url', true);
            if (!empty($blogsoftArticleURL)) {
                remove_action ('wp_head', 'rel_canonical');
                echo '<link rel="canonical" href="'.$blogsoftArticleURL.'" />' ."\n";
            }
        }
    }

    function replace_img_src($html_content) {
        $doc = new DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8'));
        $tags = $doc->getElementsByTagName('img');
        foreach ($tags as $tag) {
            $old_src = $tag->getAttribute('src');
            $new_src_url = 'http://pbx.images.nettavisen.no/?url='. $old_src . '&w=1920' ;
            $tag->setAttribute('src', $new_src_url);
        }
        return $doc->saveHTML();
    }

    function santizeChar($html_content) {
        $doc = new DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8'));
        return $doc->saveHTML();
    }

    function blogsoft_show_invalid_access_token_alter() {
        $settings = self::blogsoft_get_settings();
        if (isset($settings['blogsoft_invalid_token']) && $settings['blogsoft_invalid_token'] == 1) {
            echo "<div class='error'><p>".'<img src="'. plugins_url($this->hook.'/images/logo.png', dirname(__FILE__)). '" title="blogsoft" style="vertical-align: middle; width:17px;" />&nbsp;'."<strong>".__('You deleted Wordpress app from your blogsoft account. You have to authorize your blogsoft account again to publish article to your blogg.no blog', $this->hook)."</strong></p></div>";
        }
    }

    function blogsoft_publish_timeout_time($time) {
        $time = 60; //new number of seconds
        return $time;
    }

}

$wp_blogsoft = new WP_Blogsoft();