<?php
/*
    Plugin Name: Wp-ElasticSearch
    Plugin URI: http://www.searchbox.io
    Description: Index your WordPress site with ElasticSearch.
    Version: 1.0
    Author: Hüseyin BABAL
    Author URI: http://www.searchbox.io
    Tags: elasticsearch, index
*/
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'elasticsearch_facet_widget.php');
class Wp_ElasticSearch {

    //server settings
    var $elasticsearch_settings_server;

    //indexing settings
    var $elasticsearch_delete_post_on_remove;
    var $elasticsearch_delete_post_on_unpublish;
    var $elasticsearch_settings_index_name;

    //search result settings
    var $elasticsearch_result_tags_facet;
    var $elasticsearch_result_category_facet;
    var $elasticsearch_result_author_facet;

    //misc.
    var $version = '1.0';
    var $plugin_url = '';
    var $search_successful = false;
    var $total_num_results = 0;
    var $post_ids = NULL;
    var $per_page = 10;
    var $posts = NULL;


    function Wp_ElasticSearch() {
        //admin panel hooks
        add_action( 'admin_menu', array( &$this, 'on_admin_menu' ) );
        add_action( 'wp_ajax_elasticsearch_option_update', array( &$this, 'elasticsearch_option_update' ) );
        add_action( 'wp_ajax_elasticsearch_index_all_posts', array( &$this, 'elasticsearch_index_all_posts' ) );
        add_action( 'wp_ajax_elasticsearch_delete_all_posts', array( &$this, 'elasticsearch_delete_all_posts' ) );
        add_action( 'wp_ajax_check_server_status', array( &$this, 'elasticsearch_check_server_status' ) );
        add_action( 'wp_ajax_check_document_count', array( &$this, 'elasticsearch_check_document_count' ) );
        add_action( 'wp_print_styles', array( &$this, 'elasticsearch_theme_css' ) );
        register_activation_hook( __FILE__, array( &$this, 'on_plugin_init' ) );
        if ( !is_search() ) {
            add_action( 'widgets_init', create_function( '', 'register_widget("elasticsearch_facet_widget");' ) );
        }
        add_action( 'pre_get_posts', array( $this, 'get_posts_from_elasticsearch' ) );
        add_filter( 'the_posts', array( $this, 'get_search_result_posts' ) );

        //frontend hooks
        add_action( 'save_post', array( &$this, 'index_post' ) );
        add_action( 'delete_post', array( &$this, 'delete_post' ) );
        add_action( 'trash_post', array( &$this, 'delete_post' ) );
        //add_action( 'template_redirect', array( &$this, 'search_term') );

        //server settings
        $this->elasticsearch_settings_server = get_option( "elasticsearch_settings_server" );

        //indexing settings
        $this->elasticsearch_delete_post_on_remove = get_option( "elasticsearch_delete_post_on_remove" );
        $this->elasticsearch_delete_post_on_unpublish = get_option( "elasticsearch_delete_post_on_unpublish" );
        $this->elasticsearch_settings_index_name = get_option( "elasticsearch_settings_index_name" );

        //search result settings
        $this->elasticsearch_result_tags_facet = get_option( "elasticsearch_result_tags_facet" );
        $this->elasticsearch_result_category_facet = get_option( "elasticsearch_result_category_facet" );
        $this->elasticsearch_result_author_facet = get_option( "elasticsearch_result_author_facet" );

        //misc.
        $this->plugin_url = plugins_url() . DIRECTORY_SEPARATOR . basename( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR;
    }

    //plugin init
    function on_plugin_init() {
        //Default plugin values
        update_option( 'elasticsearch_result_category_facet', true );
        update_option( 'elasticsearch_result_tags_facet', true );
        update_option( 'elasticsearch_result_author_facet', true );
        update_option( 'elasticsearch_settings_index_name', 'wordpress' );
        update_option( 'elasticsearch_delete_post_on_remove', true );
        update_option( 'elasticsearch_delete_post_on_unpublish', true );
    }

    //admin menu option
    function on_admin_menu() {
        //add option page to settings menu on admin panel
        if (function_exists('add_menu_page')) {
            $this->pagehook = add_menu_page( 'ElasticSearch Index/Search Manager', "ElasticSearch", 'administrator', basename( __FILE__ ),
                array( &$this, 'on_show_page' ), $this->plugin_url . 'images/elasticsearch.ico' );
        } else {
            $this->pagehook = add_options_page( 'ElasticSearch', "ElasticSearch", 'manage_options', 'ElasticSearch', array( &$this, 'on_show_page' ) );
        }
        //register  option page hook
        add_action( 'load-'.$this->pagehook, array( &$this, 'on_load_page' ) );
    }

    //this page will be rendered on admin panel option page
    function on_load_page() {
        //wordpress specific js files
        wp_enqueue_script('common');
        wp_enqueue_script('wp-lists');
        wp_enqueue_script('postbox');
        wp_enqueue_script('jquery-form');

        //configuration sections
        add_meta_box('elasticsearch_server_configuration_section', 'Server Configurations', array(&$this, 'on_server_conf'), $this->pagehook, 'normal', 'core');
        add_meta_box('elasticsearch_indexing_operation_section', 'Indexing Operations', array(&$this, 'on_indexing_operations_conf'), $this->pagehook, 'normal', 'core');
        add_meta_box('elasticsearch_indexing_configuration_section', 'Indexing Configurations', array(&$this, 'on_indexing_conf'), $this->pagehook, 'normal', 'core');
        add_meta_box('elasticsearch_search_result_configuration_section', 'Search Result Configurations', array(&$this, 'on_search_result_conf'), $this->pagehook, 'normal', 'core');
    }

    //check permission and show admin panel option page
    function on_show_page() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die(__('You do not have enough privilege to access this page.'));
        }
        //we need the global screen column value to beable to have a sidebar in WordPress 2.8
        global $screen_layout_columns;

        ?>
    <div id="wp-searchbox-io" class="wrap">
        <style>.leftlabel{display:block;width:250px;float:left} .rightvalue{width:350px;} .checkwidth{width:25px;}</style>
        <?php screen_icon('options-general'); ?>
        <h2>WP ElasticSearch</h2>
        <p>WP ElasticSearch is a WordPress plugin that lets you index your entire website components by using <img src='<?php echo $this->plugin_url . "images/elasticsearch.ico"?>'/><a href="http://www.elasticsearch.org/" target="_blank">ElasticSearch</a>.</p>
        <div id="poststuff" class="metabox-holder<?php echo 2 == $screen_layout_columns ? ' has-right-sidebar' : ''; ?>">
            <div id="side-info-column" class="inner-sidebar">
                <?php /*do_meta_boxes($this->pagehook, 'side', $data);*/ ?>
            </div>
            <div id="post-body" class="has-sidebar">
                <div id="post-body-content" class="has-sidebar-content">
                    <?php do_meta_boxes($this->pagehook, 'normal', $data); ?>
                </div>
            </div>
            <br class="clear"/>
        </div>
        <p>This plugin developed by <img src='<?php echo $this->plugin_url . "images/searchbox_io.png"?>'/> <a href="http://www.searchbox.io/" target="_blank">SearchBox.io</a> cloud hosted ElasticSearch as service.</p>
    </div>
    <script type="text/javascript">
        //<![CDATA[
        jQuery(document).ready( function($) {
            // close postboxes that should be closed
            $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
            //Desired close sections
            $('#elasticsearch_indexing_configuration_section').addClass('closed');
            $('#elasticsearch_search_result_configuration_section').addClass('closed');
            // postboxes setup
            postboxes.add_postbox_toggles('<?php echo $this->pagehook; ?>');

            checkServerStatusAjax(false);
        });
        var waitImg="<img src='/wp-admin/images/loading.gif'/>";
        var options = {
            beforeSubmit:	showWait,
            success:		showResponse,
            url:			ajaxurl
        };
        // bind to the form's submit event
        jQuery('#elasticsearch_form_server_settings').submit(function($){
            var url = jQuery('#elasticsearch_settings_server').val();
            var last_char = jQuery('#elasticsearch_settings_server').val().substr(jQuery('#elasticsearch_settings_server').val().length - 1);
            if ( last_char != "/" ) {
                url = jQuery('#elasticsearch_settings_server').val() + "/";
            }
            jQuery(this).ajaxSubmit(options);
            checkServerStatusAjax(url);
            checkDocumentCountAjax(url);
            return false;
        });
        jQuery('#elasticsearch_form_indexing_settings').submit(function(){
            jQuery(this).ajaxSubmit(options);
            return false;
        });
        jQuery('#elasticsearch_form_search_result_settings').submit(function(){
            jQuery(this).ajaxSubmit(options);
            return false;
        });
        jQuery('#elasticsearch_index_all_posts').submit(function(){
            jQuery(this).ajaxSubmit(options);
            //Put delay here, because it check document count while creating documents and it always returns zero
            var s = setTimeout(function() {checkDocumentCountAjax(false);}, 1000);
            return false;
        });
        jQuery('#elasticsearch_delete_all_posts').submit(function(){
            jQuery(this).ajaxSubmit(options);
            //Put delay here, because it check document count while creating documents and it always returns zero
            var s = setTimeout(function() {checkDocumentCountAjax(false);}, 1000);
            return false;
        });
        jQuery('#elasticsearch_server_check').submit(function(){
            jQuery(this).ajaxSubmit(options);
            return false;
        });
        jQuery('#elasticsearch_settings_server').blur(function(){
            var last_char = jQuery('#elasticsearch_settings_server').val().substr(jQuery('#elasticsearch_settings_server').val().length - 1);
            if ( last_char != "/" ) {
                jQuery('#elasticsearch_settings_server').val(jQuery('#elasticsearch_settings_server').val() + "/" );
            }
        });
        // pre-submit callback
        function showWait(formData, jqForm, options){
            jqForm.children('#board').html("<div class='updated fade'>please wait... "+waitImg+"</div>");
            jqForm.find('.button-primary').attr('disabled', 'disabled');
            return true;
        }
        // post-submit callback
        function showResponse(responseText, statusText, xhr, $form){
            $form.children('#board').html(responseText);
            $form.find('.button-primary').removeAttr('disabled');
            closeResponseMessage($form.children('#board'));
        }

        //remove response text after 5 secs.
        function closeResponseMessage(elem) {
            var t = setTimeout(function() { elem.empty(); }, 5000);
        }

        function checkServerStatusAjax(serverUrl){
            var url = false;
            if (serverUrl) {
                url = serverUrl;
            }
            jQuery.post(ajaxurl, { action: "check_server_status", url: url },
                    function(data) {
                        if (data) {
                            jQuery("#server-status-div").css({'background':'green'}).attr({'title':'Running'});
                        } else {
                            jQuery("#server-status-div").css({'background':'red'}).attr({'title':'Down'});
                        }
                    }, 'json');
        }

        function checkDocumentCountAjax(serverUrl){
            var url = false;
            if (serverUrl) {
                url = serverUrl;
            }
            jQuery.post(ajaxurl, { action: "check_document_count", url: url },
                    function(data) {
                        if (data) {
                            jQuery("#document-count-div").html(data);
                        } else {
                            jQuery("#document-count-div").html("0");
                        }
                    }, 'json');
        }
        //]]>
    </script><?php
    }

    //form tag opening
    private function form_start( $form_id ) {

		$html = '<form action="#" method="post" id="' . $form_id . '">' .
		        '<input type="hidden" name="action" value="elasticsearch_option_update"/>' .
                '<input type="hidden" name="section_type" value="' . $form_id . '"/>';
        echo $html;
    }

    //build specific form element
    private function form_component( $label_text, $type, $name, $value, $disabled = false ) {
        $disabled_html = '';
        if ( $disabled ) {
            $disabled_html = " style=\"display:none;\"";
        }
        $html = '<div' . $disabled_html . '><span class="leftlabel">' . $label_text . '</span><input type="' . $type .'" ';
        if ( $type == 'checkbox' ) {
            $html .= 'value="true" class="checkwidth" ';
            if ( $value ) {
                $html .= 'checked="true" ';
            }
        } else {
            $html .= 'value="' . $value . '" class="rightvalue" ';
        }
        $html .= 'id="' . $name . '" name="' . $name . '" />';
        echo $html . "<br><br>" . '</div>' . "\n";
    }

    //form tag ending
    private function form_end() {
        $html = '<p>' .
                    '<input type="submit" value="Save Changes" class="button-primary" name="Submit"/>' .
                    '<input type="reset" value="Reset" class="button-primary" name="Reset"/>' .
                '</p>' .
                '<div id="board" name="board"></div>' .
		        '</form>';
        echo $html;
    }

    private function custom_buttons( $form_id , $button_name ) {
        $html = '<form action="#" method="post" id="' . $form_id . '">' .
            '<input type="hidden" name="action" value="' . $form_id . '"/>';
        $html .= '<p>' .
            '<input type="submit" value="' . $button_name . '" class="button-primary" name="Submit"/>' .
            '</p>' .
            '<div id="board" name="board"></div>' .
            '</form>';
        echo $html;
    }

    private function custom_status($status = 'red') {
        if ($status) {
            $background = 'green';
            $title = 'Running';
        } else {
            $background = 'red';
            $title = 'Down';
        }
        $html ='<div><span class="leftlabel">Server Status:</span> <div id="server-status-div" style="height: 20px; width: 20px; background: ' . $background . '; border-radius: 15px; position: absolute; left: 265px;" title="' . $title . '"></div></div><br/><br/><br/>';
        echo $html;
    }

    private function custom_text($label, $text) {
        $html ='<div><span class="leftlabel">' . $label . '</span> <div style="position: absolute; left: 265px;">' . $text . '</div></div><br/><br/><br/>';
        echo $html;
    }

    //server configuration section content
    function on_server_conf() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $model = new ModelPost();
        $model->serverUrl = $this->elasticsearch_settings_server;
        $this->form_start('elasticsearch_form_server_settings');
        $this->form_component( "Elasticsearch server:", "text", "elasticsearch_settings_server", $this->elasticsearch_settings_server );
        $this->custom_status( true );
        $this->custom_text( "Total Index Count:", "<span id='document-count-div' style='font-weight:bold;'>" . $model->checkIndexCount() . "</span>" );
        $this->form_end();
    }

    //indexing configuration section content
    function on_indexing_conf() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $this->form_start('elasticsearch_form_indexing_settings');
        $this->form_component( "Default Post Index Name:", "text", "elasticsearch_settings_index_name", ( ( strlen( $this->elasticsearch_settings_index_name ) < 1 ) ? ModelPost::$_TYPE : $this->elasticsearch_settings_index_name ) );
        $this->form_component( "Delete Post Index on Remove: ", "checkbox", "elasticsearch_delete_post_on_remove", $this->elasticsearch_delete_post_on_remove );
        $this->form_component( "Delete Post Index on Unpublish: ", "checkbox", "elasticsearch_delete_post_on_unpublish", $this->elasticsearch_delete_post_on_unpublish );
        $this->form_end();
    }

    //search result configuration section content
    function on_search_result_conf() {
        $this->form_start('elasticsearch_form_search_result_settings');
        $this->form_component( "Category Facet: ", "checkbox", "elasticsearch_result_category_facet", $this->elasticsearch_result_category_facet );
        $this->form_component( "Tag Facet: ", "checkbox", "elasticsearch_result_tags_facet", $this->elasticsearch_result_tags_facet );
        $this->form_component( "Author Facet: ", "checkbox", "elasticsearch_result_author_facet", $this->elasticsearch_result_author_facet );
        $this->form_end();
    }

    //Some indexing operations on admin panel option page
    function on_indexing_operations_conf() {
        $this->custom_buttons( "elasticsearch_index_all_posts", "Index All Posts" );
        $this->custom_buttons( "elasticsearch_delete_all_posts", "Delete Documents" );
    }

    function elasticsearch_option_update() {
        $options['elasticsearch_form_server_settings'] = array(
            'elasticsearch_settings_server'
        );
        $options['elasticsearch_form_indexing_settings'] = array(
            'elasticsearch_delete_post_on_remove',
            'elasticsearch_delete_post_on_unpublish',
            'elasticsearch_settings_index_name'
        );
        $options['elasticsearch_form_search_result_settings'] = array(
            'elasticsearch_result_tags_facet',
            'elasticsearch_result_category_facet',
            'elasticsearch_result_author_facet'
        );

        if ( !empty( $_POST['action'] ) && $_POST['action'] == 'elasticsearch_option_update' ) {
            foreach ( $options[$_POST['section_type']] as $section_field ) {
                $option_value = false;
                if ( array_key_exists( $section_field, $_POST ) ) {
                    $option_value = $_POST[$section_field];
                }

                update_option( $section_field, $option_value );
            }
        }

        echo "<div class='updated fade'>" . __( 'Options Updated' ) . "</div>";
        die;
    }

    /**
     * Admin panel actions start
     */
    /**
     * Index all pages ajax
     */
    function elasticsearch_index_all_posts() {
        $document_count = 0;
        if ( !empty( $_POST['action'] ) && $_POST['action'] == 'elasticsearch_index_all_posts' ) {
            $count_posts = wp_count_posts();
            if ( $count_posts->publish < 1 ) {
                echo "<div class='error fade'>" . __( 'There is no document to index' ) . "</div>";
                die;
            }
            if ( !$this->check_server_status( get_option( 'elasticsearch_settings_server' ) ) ) {
                echo "<div class='error fade'>" . __( 'Couldn\'t connect to elasticsearch server: ' . get_option( 'elasticsearch_settings_server' ) ) . "</div>";
                die;
            }
            $document_count = $this->index_all_posts();
        }
        echo "<div class='updated fade'>" . __( 'Index All Post Operation Finished, ' . $document_count . ' document(s) sent to the Elasticsearch server' ) . "</div>";
        die;
    }

    function elasticsearch_delete_all_posts() {
        if ( !empty( $_POST['action'] ) && $_POST['action'] == 'elasticsearch_delete_all_posts' ) {
            try {
                $this->delete_all_posts();
            } catch(Exception $e) {
                echo "<div class='error fade'>" . __( 'An error occured!. Be sure that you are trying to delete existing index' ) . "</div>";
                die;
            }
        }
        echo "<div class='updated fade'>" . __( 'Delete All Post Operation Finished' ) . "</div>";
        die;
    }

    /**
     * Check server status ajax
     */
    function elasticsearch_check_server_status() {
        $ret = false;
        if ( !empty( $_POST['action'] ) && $_POST['action'] == 'check_server_status' ) {
            if ( $_POST['url'] !== 'false' ) {
                $ret = $this->check_server_status( $_POST['url'] );
            } else {
                $ret = $this->check_server_status( get_option( 'elasticsearch_settings_server' ) );
            }
        }
        echo json_encode($ret);
        die;
    }

    /**
     * Check server status ajax
     */
    function elasticsearch_check_document_count() {
        $ret = false;
        if ( !empty( $_POST['action'] ) && $_POST['action'] == 'check_document_count' ) {
            if ( $_POST['url'] !== 'false' ) {
                $ret = $this->check_document_count( $_POST['url'] );
            } else {
                $ret = $this->check_document_count( get_option( 'elasticsearch_settings_server' ) );
            }
        }
        echo json_encode($ret);
        die;
    }

    /**
     * Admin panel action end
     */

    /**
     * Index post on post create/update
     * @param $post_id
     */
    function index_post( $post_id ) {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        //post
        if ( is_object( $post_id ) ) {
            $post = $post_id;
            $postId = $post->ID;
        } else {
            $post = get_post( $post_id );
        }

        if ($post->post_status != 'publish') {
            if ( get_option( 'elasticsearch_delete_post_on_unpublish' ) ) {
                $this->delete_post( $post_id );
            }
        } else {
            //tags
            $tags = get_the_tags( $post->ID );

            //url
            $url = get_permalink( $post_id );

            //categories
            $post_categories = wp_get_post_categories( $post_id );
            $cats = array();

            foreach( $post_categories as $c ) {
                $cat = get_category( $c );
                $cats[] = array( 'name' => $cat->name, 'slug' => $cat->slug );
            }

            //get author info
            $user_info = get_userdata( $post->post_author );
            $model_post = new ModelPost($post, $tags, $url, $cats, $user_info->user_login, get_option( 'elasticsearch_settings_server' ) );
            $model_post->documentPrefix = ModelPost::$_PREFIX;
            $model_post->documentIndex = get_option( 'elasticsearch_settings_index_name' );
            $model_post->index();
        }
    }

    /**
     * Delete operation on post delete or status unpublish action
     * @param $post_id
     */
    function delete_post( $post_id ) {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        if ( get_option( "elasticsearch_delete_post_on_remove" ) ) {
            $model_post = new ModelPost( null, null, null, null, null, get_option( 'elasticsearch_settings_server' ) );
            $model_post->documentIndex = get_option( 'elasticsearch_settings_index_name' );
            $model_post->delete(ModelPost::$_PREFIX . $post_id);
        }
    }

    /**
     * Loads specified theme of plugin
     */
    function elasticsearch_theme_css() {
        $name = "style-elasticsearch.css";
        $css_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . "wp-elasticsearch" . DIRECTORY_SEPARATOR . "css" . DIRECTORY_SEPARATOR . $name;
        if ( false !== @file_exists( $css_file ) ) {
            $css = $this->plugin_url . DIRECTORY_SEPARATOR . "css" . DIRECTORY_SEPARATOR . $name;
        } else {
            $css = false;
        }
        wp_enqueue_style( 'style-elasticsearch', $css, false, $this->version, 'screen' );
    }

    /**
     * Indexes all post
     */
    function index_all_posts() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $model_post = new ModelPost();
        $model_post->documentIndex = get_option( 'elasticsearch_settings_index_name' );
        $model_post->serverUrl = get_option( 'elasticsearch_settings_server' );
        if ($model_post->checkIndexExists() != 200) {
            $model_post->createIndexName();
        }
        //Default it gets last 5 posts, so give your parameters. Further detail here : http://codex.wordpress.org/Template_Tags/get_posts
        $args = array(
            'numberposts'     => 99999,
            'offset'          => 0,
            'post_status'     => 'publish',
            'orderby'         => 'post_date'
        );
        $posts = get_posts( $args );
        $document_count = 0;
        foreach ($posts as $post) {
            //tags
            $tags = get_the_tags( $post->ID );

            //url
            $url = get_permalink( $post->ID );

            //categories
            $post_categories = wp_get_post_categories( $post->ID );
            $cats = array();

            foreach( $post_categories as $c ) {
                $cat = get_category( $c );
                $cats[] = array( 'name' => $cat->name, 'slug' => $cat->slug );
            }

            //get author info
            $user_info = get_userdata( $post->post_author );
            $post_data[] = array(
                'post' => $post,
                'tags' => $tags,
                'cats' => $cats,
                'author' => $user_info->user_login,
                'uri' => $url
            );
            $document_count++;
        }

        $model_post->buildIndexDataBulk( $post_data );
        $model_post->documentIndex = get_option( 'elasticsearch_settings_index_name' );
        $model_post->documentPrefix = ModelPost::$_PREFIX;
        $model_post->serverUrl = get_option( 'elasticsearch_settings_server' );
        $model_post->checkIndexExists();
        $model_post->index(true);
        return $document_count;
    }



    function delete_all_posts() {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $model_post = new ModelPost( null, null, null, null, null, get_option( 'elasticsearch_settings_server' ) );
        $model_post->documentIndex = get_option( 'elasticsearch_settings_index_name' );
        $model_post->deleteAll();
    }

    function check_server_status( $url ) {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $model_post = new ModelPost();
        $model_post->documentIndex = get_option( 'elasticsearch_settings_index_name' );
        $model_post->serverUrl = $url;
        return $model_post->checkServerStatus();
    }

    function check_document_count( $url ) {
        require_once( "lib" . DIRECTORY_SEPARATOR . "ModelPost.php" );
        $model_post = new ModelPost();
        $model_post->documentIndex = get_option( 'elasticsearch_settings_index_name' );
        $model_post->serverUrl = $url;
        return $model_post->checkIndexCount();
    }

    function get_posts_from_elasticsearch( $wp_query ) {
        $this->search_successful = false;
        if( function_exists( 'is_main_query' ) && ! $wp_query->is_main_query() ) {
            return;
        }
        if( is_search() && ! is_admin() ) {
            global $query_string;
            global $elasticaFacets;
            $offset = 0;
            if ( get_query_var( 'page' ) != "" ) {
                $offset = ( max( 1, get_query_var( 'page' ) ) - 1 ) * 4;
            }
            $limit = 4;
            require_once( "lib" . DIRECTORY_SEPARATOR . "Searcher.php" );
            $searcher = new Searcher( get_option( 'elasticsearch_settings_server' ) );
            $facetArr = array();
            if ( get_option( 'elasticsearch_result_category_facet' ) ) {
                array_push( $facetArr, 'cats' );
            }
            if ( get_option( 'elasticsearch_result_tags_facet' ) ) {
                array_push( $facetArr, 'tags' );
            }
            if ( get_option( 'elasticsearch_result_author_facet' ) ) {
                array_push( $facetArr, 'author' );
            }
            $page = get_query_var( 'paged' );
            $offset = 0;
            if( $page > 0 ) {
                $offset = ( $page - 1 ) * $this->per_page;
            }

            //In order to use search for specfic index type, give that type to 6th parameter
            $search_results = $searcher->search( $_GET , $facetArr, $offset, $this->per_page, get_option( 'elasticsearch_settings_index_name' ), false );

            $search_result_count = $searcher->search( $_GET , $facetArr, false, false, get_option( 'elasticsearch_settings_index_name' ), false )->count();

            $elasticsearch_post_ids = array();
            $records = $search_results->getResults();
            foreach( $records as $record ) {
                $search_data = $record->getData();
                $elasticsearch_post_ids[] = $search_data['id'];
            }
            $this->total_num_results = $search_result_count;
            $this->post_ids = $elasticsearch_post_ids;
            $wp_query->query_vars['post__in'] = $this->post_ids;
            $wp_query->query_vars['posts_per_page'] = -1;
            unset($wp_query->query_vars['author']);
            unset($wp_query->query_vars['title']);
            unset($wp_query->query_vars['content']);
            $wp_query->query_vars['s'] = str_replace("*", "", $wp_query->query_vars['s']);
            $elasticaFacets = $search_results->getFacets();
            $this->search_successful = true;
        }

    }

    public function get_search_result_posts( $posts ) {

        if( ! is_search() ) {
            return $posts;
        }
        if( ! $this->search_successful ) {
            return array();
        }
        global $wp_query;
        $wp_query->max_num_pages = ceil( $this->total_num_results / $this->per_page );
        $wp_query->found_posts = $this->total_num_results;
        return $posts;
    }
}

//create an instance of plugin
if ( class_exists( 'Wp_ElasticSearch' ) ) {
    if ( !isset( $wp_elasticsearch ) ) {
        $wp_elasticsearch = new Wp_ElasticSearch;
    }
}
