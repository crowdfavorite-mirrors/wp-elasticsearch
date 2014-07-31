<?php
class ElasticSearch_Facet_Widget extends WP_Widget {
    private $widget_title = "Search Results";

    public function __construct() {
        parent::__construct(
            'widget_elasticsearch',
            'Elasticsearch Facet Widget',
            array(
                'description' => __( 'Elasticsearch Facet Widget', 'text_domain' ),
                'classname' => 'Wp_ElasticSearch'
            )
        );
    }

    function get_elasticsearch_facet_area() {
        global $elasticaFacets;
        if ( !empty( $elasticaFacets ) ): ?>
            <?php if ( ( get_option( 'elasticsearch_result_tags_facet' ) ) ): ?>
            <?php if ( !empty( $elasticaFacets['tags']['terms'] ) ): ?>
                <!-- Tags -->
                    <h5>Tags</h5>
                    <ul>
                        <?php $i = 0; ?>
                        <?php foreach ( $elasticaFacets['tags']['terms'] as $elasticaFacet ) { $i++; ?>
                        <li style="list-style-type: none;">
                            <input type="checkbox" name="facet-tag" value="<?php  echo $elasticaFacet['term']; ?>" id="tag_<?php echo $i; ?>" class="tags" onclick="searchlink('tags')" <?php echo (strpos(htmlspecialchars(urldecode($_GET['tags'])), $elasticaFacet['term']) > -1) ? "checked":""; ?>/>
                            <label for="tag_<?php echo $i; ?>" class="facet-search-link"><?php  echo $elasticaFacet['term']; ?></label>
                        </li>
                        <?php } ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( ( get_option( 'elasticsearch_result_category_facet' ) ) ): ?>
            <?php if ( !empty( $elasticaFacets['cats']['terms'] ) ): ?>
                <!-- Categories -->
                    <h5>Categories</h5>
                    <ul>
                        <?php $i = 0; ?>
                        <?php foreach ( $elasticaFacets['cats']['terms'] as $elasticaFacet ) { $i++; ?>
                        <li style="list-style-type: none;">
                            <input type="checkbox" name="facet-cats" value="<?php  echo $elasticaFacet['term']; ?>" id="cats_<?php echo $i; ?>" class="cats" onclick="searchlink('cats')" <?php echo (strpos(htmlspecialchars(urldecode($_GET['cats'])), $elasticaFacet['term']) > -1) ? "checked":""; ?>/>
                            <label for="cats_<?php echo $i; ?>" class="facet-search-link"><?php  echo $elasticaFacet['term']; ?></label>
                        </li>
                        <?php } ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( ( get_option( 'elasticsearch_result_author_facet' ) ) ): ?>
            <?php if ( !empty( $elasticaFacets['author']['terms'] ) ): ?>
                <!-- Author -->
                    <h5>Author</h5>
                    <ul>
                        <?php $i = 0; ?>
                        <?php foreach ( $elasticaFacets['author']['terms'] as $elasticaFacet ) { $i++; ?>
                        <li style="list-style-type: none;">
                            <input type="checkbox" name="facet-author" value="<?php  echo $elasticaFacet['term']; ?>" id="author_<?php echo $i; ?>" class="author" onclick="searchlink('author')" <?php echo (strpos(htmlspecialchars(urldecode($_GET['author'])), $elasticaFacet['term']) > -1) ? "checked":""; ?>/>
                            <label for="author_<?php echo $i; ?>" class="facet-search-link"><?php  echo $elasticaFacet['term']; ?></label>
                        </li>
                        <?php } ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

        <form name="search-form-hidden" id="search-form-hidden" action="<?php echo site_url(); ?>">
            <input type="hidden" name="s" id="searchbox-s" value="<?php echo ( empty( $_GET['s'] ) ) ? '' : $_GET['s']; ?>"/>
            <input type="hidden" name="tags" id="searchbox-tags" value="<?php echo ( empty( $_GET['tags'] ) ) ? '' : $_GET['tags'];?>"/>
            <input type="hidden" name="cats" id="searchbox-cats" value="<?php echo ( empty( $_GET['cats'] ) ) ? '' : $_GET['cats'];?>"/>
            <input type="hidden" name="author" id="searchbox-author" value="<?php echo ( empty( $_GET['author'] ) ) ? '' : $_GET['author'];?>"/>
        </form>
        <script type="text/javascript">
            //<![CDATA[
                function searchlink(element) {
                    var checkboxes = document.body.getElementsByClassName(element);
                    var checkArr = new Array();
                    var tempCheckArr = new Array();

                    for (var i = 0; i < checkboxes.length; i++) {
                        if (typeof tempCheckArr[checkboxes[i].value] == 'undefined') {
                            if (checkboxes[i].checked) {
                                checkArr.push(checkboxes[i].value);
                            }
                        }
                    }
                    document.getElementById("searchbox-" + element).value = checkArr.join(",");
                    document.forms["search-form-hidden"].submit();
                }
            //]]>
        </script>
        <?php endif;
    }

    //Front-end display
    function widget( $args, $instance ) {
        global $elasticaFacets;
        if ( !empty( $elasticaFacets ) ) {
            extract( $args );
            $title = apply_filters( 'widget_title', $instance['title'] );
            echo $before_widget;
            echo $before_title . $title . $after_title;
            $this->get_elasticsearch_facet_area();
            echo $after_widget;
        }
    }

    //Backend display
    function form( $instance ) {
        $form_html = '';
        $defaults = array(
            'title' => $this->widget_title
        );
        $instance = wp_parse_args( (array) $instance, $defaults );
        $form_html .= '<p>' .
                       '<label for="' . $this->get_field_id( 'title' ) . '">' . _e('Title:', 'framework') . '</label>' .
			           '<input type="text" class="widefat" id="' . $this->get_field_id( 'title' ) . '" name="' . $this->get_field_name( 'title' ) . '" value="' . $instance['title'] . '" />' .
		              '</p>';
        echo $form_html;
    }

    //Backend update for elasticsearch widget
    function update( $new_instance, $old_instance ) {
        $instance = $old_instance;
        $instance['title'] = strip_tags( $new_instance['title'] );
        return $instance;
    }
}