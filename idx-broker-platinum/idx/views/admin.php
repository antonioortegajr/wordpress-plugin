<?php
namespace IDX\Views;

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

global $api_error;
$idx_api = new \IDX\Idx_Api;

if (!$api_error) {
    $system_links = $idx_api->idx_api_get_systemlinks();
    if (is_wp_error($system_links)) {
        $api_error = $system_links->get_error_message();
    }
}
/**
 * check wrapper page exist or not
 */
$wrapper_page_id = get_option('idx_broker_dynamic_wrapper_page_id');
$post_title = '';
$wrapper_page_url = '';
if ($wrapper_page_id) {
    if (!get_page_uri($wrapper_page_id)) {
        update_option('idx_broker_dynamic_wrapper_page_id', '');
        $wrapper_page_id = '';
    } else {
        $post_title = get_post($wrapper_page_id)->post_title;
        $wrapper_page_url = get_page_link($wrapper_page_id);
    }
}
?>

<div id="idxPluginWrap" class="wrap">
    <div>
        <h2 class="flft">IDX Broker&reg; Plugin Settings</h2>
        <div class="useful-links">
            <ul class="usefulLinks">
                <li><a href="http://support.idxbroker.com/customer/portal/articles/1917460-wordpress-plugin" target="_blank">IDX Knowledgebase</a></li>
                <li><a href="https://middleware.idxbroker.com/mgmt/login.php" target="_blank">IDX Control Panel</a></li>
            </ul>
        </div>
        <a href="http://www.idxbroker.com" target="_blank" class="logo-link">
                <div id="logo"></div>
        </a>
    </div>
    <form method="post" action="options.php" id="idx_broker_options">
        <?php wp_nonce_field('update-options');?>
        <div id="blogUrl" style="display: none;" ajax="<?php bloginfo('wpurl');?>"></div>
                <div id="genSettings">
                    <h3 class="hndle">
                        <label>Get an API Key</label>
                        <a href="http://kb.idxbroker.com/index.php?/Knowledgebase/Article/View/98/16/idx-broker-platinum-wordpress-plugin" class="helpIcon" target="_blank"></a>
                    </h3>
                    <div class="help-text">You will find your API Key under Home > API Control in your IDX Control Panel.</div>
                    <div class="inlineBlock">
                        <div>
                            <label for="idx_broker_apikey">Enter Your API Key: </label>
                            <input name="idx_broker_apikey" type="text" id="idx_broker_apikey" value="<?php echo get_option('idx_broker_apikey');?>" />
                            <input type="button" name="api_update" id="api_update" value="Refresh Plugin Options" class="button-primary" style="width:auto;" />
                            <span class="refresh_status"></span>
                        </div>
                        <p class="error hidden" id="idx_broker_apikey_error">
                            Please enter your API key to continue.
                            <br>
                            If you do not have an IDX Broker account, please contact the IDX Broker team at 800-421-9668.
                        </p>
                        <?php
if ($api_error) {
    echo '<p class="error" style="display:block;">' . $api_error . '</p>';
}
?>
                    </div>
                </div>
                <!-- dynamic wrapper page -->
                <div id="dynamic_page">
                    <h3>Set up the Global Wrapper<a href="http://kb.idxbroker.com/Knowledgebase/Article/View/189/0/automatically-create-dynamic-wrapper-page-in-wordpress" target="_blank"><img class="help-icon" src="<?php echo plugins_url('../../assets/images/helpIcon.svg', __FILE__);?>" alt="help"></a></h3>
                    <div class="help-text">Setting this up will match the IDX pages to your website design automatically every few hours.<div>Example: Properties</div></div>
                    <label for="idx_broker_dynamic_wrapper_page_name">Page Name:</label>
                    <input name="idx_broker_dynamic_wrapper_page_name" type="text" id="idx_broker_dynamic_wrapper_page_name" value="<?php echo $post_title;?>" />
                    <input name="idx_broker_dynamic_wrapper_page_id" type="hidden" id="idx_broker_dynamic_wrapper_page_id" value="<?php echo get_option('idx_broker_dynamic_wrapper_page_id');?>" />
                    <input type="button" class="button-primary" id="idx_broker_create_wrapper_page" value="<?php echo $post_title ? 'Update' : 'Create'?>" />
                    <?php
if ($wrapper_page_id != '') {
    ?>
                        <input type="button" class="button-primary" id="idx_broker_delete_wrapper_page" value="Delete" />
                    <?php
}
?>
                    <span class="wrapper_status"></span>
                    <p class="error hidden">Please enter a page title</p>
                    <span id="protocol" class="label hidden"></span>
                    <input id="page_link" class="hidden" type="text" value="<?php echo $wrapper_page_url;?>" readonly>
                </div>
    <?php settings_fields('idx-platinum-settings-group');?>
    </form>

</div>