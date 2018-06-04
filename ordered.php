<?php require_once("header.php"); ?>
<?php require_once("sidebar.php"); ?>

<!-- Page Content -->
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <h1 class="page-header">Ordered Results</h1>
            </div>
            <!-- /.col-lg-12 -->
        </div>
        <div class="row">
            <form id="search-form">
                <div class="col-lg-6">
                    <div class="input-group">
                        <input type="text" id="inputSearch" class="form-control" placeholder="Search for...">
                            <span class="input-group-btn">
                                <button id="btnSearch" class="btn btn-default" type="submit">Search</button>
                            </span>
                    </div>
                    <!-- /input-group -->
                </div>
                <!-- /.col-lg-6 -->
            </form>
        </div>
        <div class="row">
            <div class="col-md-6">
                <h1>mysql</h1>
                <span id="mysqlTimer" class="text-success timer"></span>
                <div id="mysql-results">

                </div>
            </div>
            <div class="col-md-6">
                <h1>solr</h1>
                <span id="solrTimer" class="text-success timer"></span>
                <div id="solr-results">
            </div>
        </div>
        <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
</div>
<!-- /#page-wrapper -->

<?php require_once("footer.php"); ?>

<script type="text/javascript">
    $('#mysqlTimer').runner(
        {
            format: function (value) {
                return value + ' ms';
            }
        }
    );
    $('#solrTimer').runner(
        {
            format: function (value) {
                return value + ' ms';
            }
        }
    );

    $("#search-form").submit(function () {
        var query = $("#inputSearch").val();

        if (validate(query)) {
            sendMysqlSearchRequest(query);
            sendSolrSearchRequest(query);
        }

        return false;
    });

    function sendMysqlSearchRequest(query) {
        var action = 'search-mysql';
        var order = 'name';

        $.ajax({
            url: "http://localhost/solr-demo/controller/search-controller.php",
            data: {'q': query, 'action': action, 'order':order},
            dataType: 'json',
            beforeSend: function () {
                var timer = $('#mysqlTimer');
                timer.runner('reset');
                timer.runner('start');
            }
        })
            .done(function (response) {
                $('#mysqlTimer').runner('stop');
                $('#mysql-results').html('');
                $.each(response.data, function(index, element) {
                    $('#mysql-results').append($('<div>', {
                        text: element.name
                    }));
                });
            })
    }

    function sendSolrSearchRequest(query) {

        var action = 'search-solr';
        var order = 'name';

        $.ajax({
            url: "http://localhost/solr-demo/controller/search-controller.php",
            data: {'q': query, 'action': action, 'order':order},
            dataType: 'json',
            beforeSend: function () {
                var timer = $('#solrTimer');
                timer.runner('reset');
                timer.runner('start');
            }
        })
            .done(function (response) {
                $('#solrTimer').runner('stop');
                $('#solr-results').html('');
                $.each(response.data, function(index, element) {
                    $('#solr-results').append($('<div>', {
                        text: element.name
                    }));
                });
            })
    }

    function validate(query) {
        if (!query) {
            alert("Please enter search query");
            return false;
        }
        return true;
    }
</script>