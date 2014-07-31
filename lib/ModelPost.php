<?php
require_once ('ModelBase.php');
class ModelPost extends ModelBase {

    var $post;
    var $tags;
    var $url;
    var $author;
    var $cats;

    public static $_PREFIX = 'post_';
    public static $_TYPE = 'post';
    public static $_INDEX = 'wordpress';

    protected static $fieldsToIndex = array(
        'post_date' => 'date',
        'post_content' => 'content',
        'post_title' => 'title',
        'post_excerpt' => 'excerpt',
        'post_tags' => 'tags',
        'ID' => 'id'
    );

    function __construct($post = null, $tags = null, $url = null, $cats = null, $author = null, $serverUrl = null) {
        spl_autoload_register( array( $this, '__autoload_elastica' ) );
        $this->post = $post;
        $this->tags = $tags;
        $this->url = $url;
        $this->cats = $cats;
        $this->author = $author;
        $this->serverUrl = $serverUrl;
        $this->documentType = ($this->documentType != null) ? $this->documentType : ModelPost::$_TYPE;
        $this->documentIndex = ($this->documentIndex != null) ? $this->documentIndex : ModelPost::$_INDEX;
        $this->buildIndexData();
    }
    function __autoload_elastica ($class) {
        $path = str_replace('_', DIRECTORY_SEPARATOR, $class);
        if (file_exists(dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $path . '.php')) {
            require_once(dirname( __FILE__) . DIRECTORY_SEPARATOR . $path . '.php');
        }
    }


    public function buildIndexData() {
        //post fields
        foreach (self::$fieldsToIndex as $fieldName => $nameInIndex) {
            $documentToIndex[$nameInIndex] = $this->post->$fieldName;
        }

        //tags
        $tags_array = array();
        if ( $this->tags != NULL ) {
            foreach( $this->tags as $tag ) {
                array_push( $tags_array, $tag->name );
            }
            $documentToIndex['tags'] = $tags_array;
        }

        //categories
        $cats_array = array();
        if ( $this->cats != NULL ) {
            foreach( $this->cats as $category ) {
                array_push( $cats_array, array($category['name']) );
            }
            $documentToIndex['cats'] = $cats_array;
        }

        //post author
        $documentToIndex['author'] = $this->author;

        //post uri
        $documentToIndex['uri'] = $this->url;

        $this->documentToIndex = $documentToIndex;
    }

    public function buildIndexDataBulk($posts = array()) {
        if (empty($posts)) {
            throw new Exception("Bulk index array cannot be empty");
        }
        foreach($posts as $post) {
            //post fields
            foreach (self::$fieldsToIndex as $fieldName => $nameInIndex) {
                $documentToIndex[$nameInIndex] = $post['post']->$fieldName;
            }

            //tags
            $tags_array = array();
            if ( $post['tags'] != NULL ) {
                foreach( $post['tags'] as $tag ) {
                    array_push( $tags_array, $tag->name );
                }
                $documentToIndex['tags'] = $tags_array;
            }

            //categories
            $cats_array = array();
            if ( $post['cats'] != NULL ) {
                foreach( $post['cats'] as $category ) {
                    array_push( $cats_array, $category['name'] );
                }
                $documentToIndex['cats'] = $cats_array;
            }

            //post author
            $documentToIndex['author'] = $post['author'];

            //post uri
            $documentToIndex['uri'] = $post['uri'];
            $documentsToIndex[] = $documentToIndex;
        }
        $this->documentToIndex = $documentsToIndex;
    }
}