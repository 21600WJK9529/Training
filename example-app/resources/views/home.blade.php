<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .table-container {
            max-height: 670px;
            overflow-y: auto;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            function getAndDisplayRates() {
                $.ajax({
                    url: '/get-rates', // Adjust the URL based on your Laravel routes
                    method: 'GET',
                    success: function (response) {
                        updateRatesTables(response);
                    },
                    error: function (error) {
                        console.error('Error fetching rates:', error);
                    }
                });
            }

            function updateRatesTables(rates) {
                // Update the first table
                updateTable('#forexTable', rates[0]);

                // Update the second table
                updateTable('#cryptoTable', rates[1]);
            }

            function updateTable(tableId, data) {
                var tableHtml = '<thead class="thead-dark"><tr><th>Currency</th><th>Rate</th></tr></thead><tbody>';

                for (var currency in data) {
                    var value = data[currency];
                    tableHtml += '<tr><td>' + currency + '</td><td>' + value + '</td></tr>';
                }

                tableHtml += '</tbody>';

                // Update the specified table content
                $(tableId).html(tableHtml);
            }

            $('#getRatesButton').on('click', function () {
                getAndDisplayRates();
            });
        });
    </script>
</head>

<body class="antialiased">
    <div class="container mt-4">
        <div class="row">
            <div class="col">
                <h3>Forex Table</h3>
                <div class="table-container">
                    <table id="forexTable" class="table table-bordered table-striped">
                        <!-- Forex Table content will be dynamically updated here -->
                    </table>
                </div>
            </div>
            <div class="col">
                <h3>Crypto Table</h3>
                <div class="table-container">
                    <table id="cryptoTable" class="table table-bordered table-striped">
                        <!-- Crypto Table content will be dynamically updated here -->
                    </table>
                </div>
            </div>
        </div>
        <button id="getRatesButton" class="btn btn-primary mt-3">Get Forex Rates</button>
    </div>
</body>

</html>
