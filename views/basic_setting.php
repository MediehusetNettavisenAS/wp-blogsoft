<?php

$settings = self::blogsoft_get_settings();

/* wrap
*********************************************************************************/
echo '<div class="wrap">' . get_screen_icon('blogsoft-lock');
echo '<h2>' . $this->pluginname . ' : ' . __('Connect to Blogsoft', $this->hook) . '<sup style="color:#f81865; padding: 3px; font-size: 15px; font-weight:bold;">Beta</sup></h2>';

/* show a warning
*------------------------------------------------------------*/

if (isset($_GET['error'])) {
    echo "<div class='error'><p><strong>" . $_GET['error'] . "</strong></p></div>";
}

if (isset($_POST['blogsoft_update_settings'])) {
    echo '<div class="updated fade"><p><strong>' . __('Settings updated', $this->hook) . '.</strong></p></div>';
}
?>

<?=self::blogsoft_show_invalid_access_token_alter();?>

<div id="poststuff">
    <div id="post-body" class="metabox-holder">
        <div class="postbox-container">
            <div class="meta-box-sortables">

                <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>" name="frmBlogsoftSettings" id="frmBlogsoftSettings">
                    <input type="hidden" name="info_update" id="info_update" value="true"/>

                    <div class="postbox">
                        <div class="handlediv" title="<?php _e('Click to toggle', $this->hook) ?>"><br/></div>
                        <h3 class='hndle'><span><?php _e('Connect to Blogsoft', $this->hook) ?></span></h3>

                        <div class="inside">

                            <?php if (!$settings['access_token']) { ?>

                                <div class="blogsoft-left-content">
                                    <p><?php _e('uses OAuth authentication to connect to Blogsoft. Follow the authentication process below to authorise this Plugin to access on your Blogsoft account.', $this->hook) ?></p>

                                    <a href="<?=self::blogsoft_get_auth_url();?>" title="Authorize"><?php _e('Authorise', $this->hook) ?></a>


                                </div><!-- left content -->

                                <!--<div class="blogsoft-right-content">
                                    <?php /*$auth_url = self::blogsoft_get_auth_url(); */?>
                                    <?php /*if ($auth_url) { */?>
                                        <p><a href="<?php /*echo $auth_url; */?>"
                                              title="<?php /*_e('Sign in with Blogsoft', $this->hook); */?>"><img
                                                    src="<?php /*echo plugins_url('images/logo.png', dirname(__FILE__)); */?>"
                                                    alt=""><?php /*_e('Sign in with Blogsoft', $this->hook); */?></a></p>

                                    <?php /*} else { */?>
                                        <h4><?php /*_e('Not able to validate access to account, Blogsoft is currently unavailable. Try checking again in a couple of minutes.', $this->hook); */?></h4>
                                    <?php /*} */?>


                                </div>--><!-- right content -->
                            <?php } else { ?>
                                <div class="blogsoft-left-content">


                                    <h2><?php _e('Blog URL =', $this->hook) ?> <a
                                            href="<?php echo $settings['bloggno_blog_url']; ?>"
                                            data-width="700" data-height="500" rel="1" id="bloggno_blog_url"
                                            class="newWindow"> <?php echo $settings['bloggno_blog_url']; ?></a></h2>

                                </div><!-- left content -->

                                <div class="blogsoft-right-content">
                                    <p><?php _e('Your account has  been authorized.', $this->hook); ?> (<strong><a
                                                href="<?php echo $_SERVER['REQUEST_URI']; ?>&blogsoft=deauthorize"
                                                onclick="return confirm( '<?php _e('Are you sure you want to deauthorize your Blogsoft account?', $this->hook); ?>');"><?php _e('Deauthorize', $this->hook); ?></a></strong>)
                                    </p>
                                </div><!-- right content -->
                            <?php } ?>

                            <div class="clear"></div>

                        </div>
                    </div>
                    <?php
                        if (isset($settings['access_token']) && !empty($settings['access_token'])) {
                            $blogsoftCategories = self::blogsoft_categories();
                    ?>

                        <div class="postbox">
                            <div class="handlediv" title="<?php _e('Click to toggle', $this->hook) ?>"><br/></div>
                            <h3 class='hndle'><span><?php _e('Basic Settings', $this->hook) ?></span></h3>

                            <div class="inside">
                                <!-- ############################################################################################################### -->
                                <!-- left content -->
                                <div class="blogsoft-left-content">
                                    <p><input type="checkbox" class="check" id="publish_in_blogsoft"
                                              name="publish_in_blogsoft"<?php if ($settings['publish_in_blogsoft']) echo ' checked'; ?> />
                                        <strong><?php _e('Publish in blogsoft when a post is published/edited', $this->hook); ?></strong>
                                    </p>
                                </div>
                                <!-- right content -->
                                <div class="clear"></div>

                                <div class="blogsoft-left-content blogsoft_active">
                                    <p><input type="checkbox" class="check" id="blogsoft_seo"
                                              name="blogsoft_seo"<?php if ($settings['blogsoft_seo']) echo ' checked'; ?> />
                                        <strong><?php _e('I want search engines credits goes to blogsoft', $this->hook); ?></strong>
                                    </p>
                                </div>
                                <!-- right content -->
                                <div class="clear"></div>

                                <div class="blogsoft-left-content blogsoft_active">
                                    <p>
                                        <strong><?php _e('Default Category', $this->hook); ?></strong>&nbsp;&nbsp;
                                        <select name="bloggno_category_id" id="bloggno_category_id">
                                            <?php
                                                foreach($blogsoftCategories as $key => $category) {
                                            ?>
                                                <option value="<?php echo $key ; ?>" <?php if($key == $settings['bloggno_category_id']) { echo 'selected="selected"';} ?>><?php echo $category ; ?></option>
                                            <?php
                                                }
                                            ?>
                                        </select>
                                    </p>
                                </div>
                                <!-- right content -->
                                <div class="clear"></div>

                                <!-- ############################################################################################################### -->
                            </div>

                            <div class="inside blogsoft_active">
                                <h2><?php _e( 'Category Mapping:', $this->hook); ?></h2>
                                <?php wp_dropdown_categories( 'hide_empty=0&show_count=0&hierarchical=1&show_option_all=Select Wordpress Category' ); ?>
                                 ==
                                <select name="blogsoftCategories" id="blogsoftCategories">
                                    <option value="0" selected="selected" ><?php _e('Select Blogsoft Category', $this->hook) ?></option>
                                    <?php
                                        foreach($blogsoftCategories as $key => $category) {
                                    ?>
                                            <option value="<?php echo $key ; ?>"><?php echo $category ; ?></option>
                                    <?php
                                        }
                                    ?>
                                </select>

                                <input type="button" title="Add Rule" value="<?php _e('Add Rule', $this->hook) ?>" id="addBlogsoftRule"/>

                            </div>

                            <div class="inside blogsoft_active">
                                <div class="blogsoft-left-content">
                                    <?php
                                        $ruleIndex = 0;
                                        $blognoRules = unserialize($settings['bloggno_rules']);
                                    ?>
                                    <ul id="blogsoftRules">
                                         <?php
                                            if(sizeof($blognoRules)) {
                                                foreach($blognoRules as $wp_cat => $bs_cat) {
                                         ?>
                                                    <li class="list-group-item">
                                                        <input type="hidden" value="<?php echo $wp_cat; ?>" name="wp_cat[<?php echo $ruleIndex; ?>]" class="wp_cat" />
                                                        <input type="hidden" value="<?php echo $bs_cat; ?>" name="bs_cat[<?php echo $ruleIndex; ?>]" class="bs_cat" />
                                                        <span style="float:right;"><a href="#">Remove</a></span>
                                                        <?php echo get_cat_name($wp_cat); ?> --> <?php echo $blogsoftCategories[$bs_cat]; ?>
                                                    </li>
                                         <?php
                                                    $ruleIndex++;
                                                }
                                            }
                                         ?>
                                     </ul>

                                    <script type="text/javascript">
                                        var ruleIndex = <?php echo $ruleIndex; ?>
                                    </script>
                                </div>

                                <div class="clear"></div>

                            </div>

                        </div>

                        <div class="button_submit">
                            <?php echo submit_button(__('Save all options', $this->hook), 'primary', 'blogsoft_update_settings', false); ?>
                        </div>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
</div>
</div>



