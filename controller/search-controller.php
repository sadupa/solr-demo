<?php
require_once(dirname(__FILE__) . "/../lib/class.dbQuery.php");
require_once(dirname(__FILE__) . "/../lib/class.ajaxResponse.php");

$action = !empty($_GET['action']) ? $_GET['action'] : null;
$query = !empty($_GET['q']) ? $_GET['q'] : null;
$order = !empty($_GET['order']) ? $_GET['order'] : null;
$limit = 50;

$response = new AjaxResponse();
if (empty($query)) {
    $response->setStatus(AjaxResponse::STATUS_ERROR);
    $response->setMessage("Search query required");
    echo($response->getResponse());
    return;
}

switch ($action) {
    case "search-mysql":
        getMysqlSearchResult($query, $order, $limit);
        break;
    case "search-solr":
        getSolrResult($query, $order, $limit);
        break;
    default:
        header("HTTP/1.1 403 Forbidden");
        exit;
}


function getMysqlSearchResult($query, $order, $limit)
{
    $response = new AjaxResponse();

    try {
        $dbQuery = new DbQuery();
        $sql = "SELECT * FROM personal_name WHERE name LIKE '%" . $query . "%'";
        if (!empty($order)) {
            $sql .= " ORDER BY " . $order;
        }
        $sql .= " LIMIT " . $limit;
        $names = $dbQuery->executeSqlQuery($sql);
        $response->getStatus(AjaxResponse::STATUS_SUCCESS);
        $response->setData($names);
        $response->setMessage($sql);
        echo($response->getResponse());
    } catch (Exception $e) {
        error_log("Exception occurred while trying to search in mysql: " . $e);
        $response->setStatus(AjaxResponse::STATUS_FAIL);
        echo($response->getResponse());
    }
}

function getSolrResult($query, $order, $limit)
{
    $response = new AjaxResponse();
    $query = str_replace(' ', '', $query);
    try {
        $solr_query = "q=name_searchable:*" . urlencode($query) . "*&rows=50&wt=json";
        if (!empty($order)) {
            $order = "name_sortable asc";
            $solr_query .= "&sort=" . urlencode($order);
        }
        $solr_query .= "&rows=" . $limit;
        $solr_query .= "&wt=json";

        $solr_response = file_get_contents("http://localhost:8983/solr/solr_demo/select?" . $solr_query);
        if ($solr_response === FALSE) {
            $response->setStatus(AjaxResponse::STATUS_FAIL);
            echo($response->getResponse());
            return;
        }

        $response_json = json_decode($solr_response, true);
        $response->setData($response_json["response"]["docs"]);
        $response->setStatus(AjaxResponse::STATUS_SUCCESS);
        $response->setMessage($solr_query);
        echo($response->getResponse());
    } catch (Exception $e) {
        error_log("Exception occurred while trying to search in solr: " . $e);
        $response->setStatus(AjaxResponse::STATUS_FAIL);
        echo($response->getResponse());
    }
}