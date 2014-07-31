<?php
abstract class ModelBase {

    var $serverUrl = null; //It should be end with "/". https://github.com/ruflin/Elastica/issues/120#issuecomment-3423869
    var $elasticaClient = null;
    var $documentToIndex = null;
    var $documentType = null;
    var $documentIndex = null;
    var $documentPrefix = null;

    public static $_CHUNK_SIZE = 1000;

    function initialize() {
        spl_autoload_register(array( $this, '__autoload_elastica'));
        $this->elasticaClient = new Elastica_Client(
            array(
                'url' => $this->serverUrl
            )
        );
    }
    function __autoload_elastica ($class) {
        $path = str_replace('_', DIRECTORY_SEPARATOR, $class);
        if (file_exists(dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $path . '.php')) {
            require_once(dirname( __FILE__) . DIRECTORY_SEPARATOR . $path . '.php');
        }
    }

    /**
     * Index document by using its id. documentPrefix is used for making index unique
     * among all objects(post, comment, user, etc...)
     */
    public function index($bulk = false) {
        if (!empty( $this->documentToIndex )) {
            if ($this->elasticClient == null) {
                $this->initialize();
            }
            $elasticaIndex = $this->elasticaClient->getIndex($this->documentIndex);
            $elasticaType = $elasticaIndex->getType($this->documentType);
            if ($bulk) {
                $i = 0;
                foreach ($this->documentToIndex as $doc) {
                    $documents[] = new Elastica_Document($this->documentPrefix . $doc['id'], $doc);
                    $i++;
                    //bulk index is better than unit index.
                    if ($i % ModelBase::$_CHUNK_SIZE == 0) {
                        $elasticaType->addDocuments($documents);
                        $documents = array();
                    }
                }
                if (!empty($documents)) {
                    $elasticaType->addDocuments($documents);
                }
            } else {
                $document = new Elastica_Document($this->documentPrefix . $this->documentToIndex['id'], $this->documentToIndex);
                $elasticaType->addDocument($document);
            }
            $elasticaType->getIndex()->refresh();
        }
    }

    /**
     * Delete specific index
     * @param $documentId
     */
    public function delete($documentId) {
        if ($this->elasticClient == null) {
            $this->initialize();
        }
        $elasticaIndex = $this->elasticaClient->getIndex($this->documentIndex);
        $elasticaType = $elasticaIndex->getType($this->documentType);
        $elasticaType->deleteById($documentId);
        $elasticaType->getIndex()->refresh();
    }

    /**
     * Delete entire type(all indexes)
     */
    public function deleteAll() {
        if ($this->elasticClient == null) {
            $this->initialize();
        }
        $elasticaIndex = $this->elasticaClient->getIndex($this->documentIndex);
        $elasticaType = $elasticaIndex->getType($this->documentType);
        $elasticaType->delete();
        $elasticaType->getIndex()->refresh();
    }


    /**
     * Check index existance
     */
    public function checkIndexExists() {
        $url = $this->serverUrl . $this->documentIndex;
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $http_status;
    }

    /**
     * Check index existance
     */
    public function checkServerStatus() {
        $url = $this->serverUrl;
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $statusData = json_decode(curl_exec($ch), true);
        curl_close ($ch);
        return ($statusData['status'] == '200');
    }

    /**
     * Create index with curl
     */
    public function createIndexName() {
        $url = $this->serverUrl . $this->documentIndex;
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        $result = curl_exec($ch);
        return $result;
    }

    /**
     * Check index count
     */
    public function checkIndexCount() {
        $url = $this->serverUrl . $this->documentIndex . '/_stats';
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $indexData = json_decode(curl_exec($ch), true);
        curl_close ($ch);
        return $indexData['_all']['primaries']['docs']['count'];
    }
}
