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
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" id="inputSearch" class="form-control" placeholder="Search for...">
                            <span class="input-group-btn">
                                <button id="btnSearch" class="btn btn-default" type="submit">Search</button>
                            </span>
                    </div>
                </div>
                <div class="col-md-push-4 col-md-2">
                    <select id="limit" class="form-control" name="limit">
                        <option>50</option>
                        <option>100</option>
                        <option>500</option>
                        <option>1000</option>
                        <option>5000</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="row">
            <div class="col-md-6">
                <h1>mysql</h1>
                <span id="mysqlTimer" class="text-success timer"></span>

                <div id="mysql-results"></div>
                <div id="mysql-query"></div>
            </div>
            <div class="col-md-6">
                <h1>solr</h1>
                <span id="solrTimer" class="text-success timer"></span>

                <div id="solr-results"></div>
                <div id="solr-query"></div>
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
            sendSearchRequest();
            return false;
        });

        $("#limit").change(function () {
                if ($("#inputSearch").val()) {
                    sendSearchRequest();
                }
            }
        );

        function sendSearchRequest() {
            var query = $("#inputSearch").val();
            var limit = $("#limit").val();

            if (validate(query)) {
                sendMysqlSearchRequest(query, limit);
                sendSolrSearchRequest(query, limit);
            }
        }

        function sendMysqlSearchRequest(query, limit) {
            var action = 'search-mysql';
            var order = 'name';

            $.ajax({
                url: "http://localhost/solr-demo/controller/search-controller.php",
                data: {'q': query, 'action': action, 'order': order, 'limit': limit},
                dataType: 'json',
                beforeSend: function () {
                    var timer = $('#mysqlTimer');
                    timer.runner('reset');
                    timer.runner('start');
                }
            })
                .done(function (response) {
                    $('#mysqlTimer').runner('stop');
                    $('#mysql-query').html(response.message);
                    $('#mysql-query').addClass('well well-sm');
                    $('#mysql-results').html('');
                    $.each(response.data, function (index, element) {
                        $('#mysql-results').append($('<div>', {
                            text: element.name
                        }));
                    });
                })
        }

        function sendSolrSearchRequest(query, limit) {

            var action = 'search-solr';
            var order = 'name';

            $.ajax({
                url: "http://localhost/solr-demo/controller/search-controller.php",
                data: {'q': query, 'action': action, 'order': order, 'limit': limit},
                dataType: 'json',
                beforeSend: function () {
                    var timer = $('#solrTimer');
                    timer.runner('reset');
                    timer.runner('start');
                }
            })
                .done(function (response) {
                    $('#solrTimer').runner('stop');
                    $('#solr-query').html(response.message);
                    $('#solr-query').addClass('well well-sm');
                    $('#solr-results').html('');
                    $.each(response.data, function (index, element) {
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