(function browse() {
    var url = new URL(window.location);
    var resource = url.searchParams.get("u");
    var endpoint = '/api/' + resource;
    var callNames = {
        1: 'Attack',
        2: 'Counter Attack',
        3: 'Riposte',
        4: 'Remise',
        5: 'Line',
        6: 'Other',
        7: 'Simultaneous'
    };

    function displayObject(data) {
        var $display = $('#display');

        Object.keys(data).forEach(function (key, index) {
            var title;

            if (key === 'children') {
                data[key].forEach(function (child) {
                    $display.append('<div><a href="' + window.location + '/' + child + '">' + child + '</a></div>');
                });
            } else if (key === 'call_percentages') {
                var callPercentages = data[key];
                var forAgainst;
                var $allCallsWrapper =$('<div class="container-fluid"></div>');
                var $allCalls = $('<div class="row"></div>')
                for (forAgainst in callPercentages) {
                    var calls = callPercentages[forAgainst];
                    var callId;
                    var callName;
                    var callPercent;
                    var differenceFromAverage;
                    var differenceStyle;
                    var style;
                    title = 'Actions';

                    if (forAgainst === 'for') {
                        title = 'Actions For';
                    } else if (forAgainst === 'against') {
                        title = 'Actions Against';
                    } else if (forAgainst === 'average_fencer') {
                        continue;
                    }

                    var $calls = $('<div class="col-md-6 calls"></div>');
                    $calls.append('<h5>' + title + '</h5>');

                    for (callId in calls) {
                        callName = callNames[callId];
                        callPercent = Math.round(calls[callId] * 100);
                        differenceFromAverage = Math.round((calls[callId] - callPercentages['average_fencer'][callId]) * 100);
                        differenceStyle = 'color: gray;';
                        if (differenceFromAverage > 0) {
                            differenceStyle = 'color: green;';
                        } else if (differenceFromAverage < 0) {
                            differenceStyle = 'color: darkblue;';
                        }
                        style = "width: " + callPercent + "%; white-space: nowrap;";
                        $calls.append(
                            '<div><span>'
                            + callName
                            + '</span> : <div class="graph-bar" style="'
                            + style
                            + '">'
                            + callPercent
                            + '%'
                            + ' <span style="'
                            + differenceStyle
                            +'">('
                            + differenceFromAverage
                            + '%)</span></div></div>');
                    }

                    $allCalls.append($calls);
                }

                $allCallsWrapper.append($allCalls);
                $display.append($allCallsWrapper);
            } else if (key === 'photo_url') {
                $display.append('<div><img height="80" src="' +  data[key] + '"></div>');
            } else {
                var value = data[key];
                if (typeof value === 'object') {
                    value = JSON.stringify(value);
                }
                title = key.charAt(0).toUpperCase() + key.slice(1);
                title = title.replace(/_/g, " ");

                $display.append('<div><span>' + title + '</span> : <span>' + value + '</span></div>');
            }
        });
    }

    function makeBrowseTable(data) {
        var firstRecord = data[0];

        var endpointArray = endpoint.split('/');
        var resource = endpointArray[endpointArray.length - 1];
        var columns = [];
        var $list = $('#list');

        Object.keys(firstRecord).forEach(function (key, index) {
            var title = key.charAt(0).toUpperCase() + key.slice(1);
            title = title.replace(/_/g, " ");
            var column = {
                'data': key,
                'title': title
            };

            column.render = function (data, type, row) {
                var rowData = row;
                var link =  '/browse?u=' + resource + '/' + rowData.id;

                if (rowData.link !== undefined) {
                    link = rowData.link
                }

                return '<a href="' + link + '">' + data + '</a>';
            };

            if (key === 'thumb' || key === 'photo_url') {
                column.render = function (data, type, row) {
                    return '<img width="120" src="' + data + '" />';
                }
            }

            if (key !== 'thumb' && key !== 'link') {
                columns.push(column);
            }
        });

        var $footer = $('<tfoot></tfoot>');
        var $footerRow = $('<tr></tr>');
        columns.forEach(function () {
            $footerRow.append('<th></th>');
        });
        $footer.append($footerRow);
        $list.append($footer);


        var $table = $list.DataTable({
            "columns": columns,
            "data": data,
            "responsive": true,
            "dom": 'Bfrtip',
            "buttons": [
                'colvis', 'pageLength'
            ],
            "lengthMenu": [
                [ 10, 25, 50, 100, -1 ],
                [ '10 rows', '25 rows', '50 rows', '100 rows', 'Show all' ]
            ],
            initComplete: function () {
                this.api().columns().every(function () {
                    var column = this;
                    var select = $('<select><option value=""></option></select>')
                        .appendTo($(column.footer()))
                        .on('change', function () {
                            var val = $.fn.dataTable.util.escapeRegex(
                                $(this).val()
                            );

                            column
                                .search(val ? '^' + val + '$' : '', true, false)
                                .draw();
                        });

                    column.data().unique().sort().each(function (d, j) {
                        if (d !== '') {
                            select.append('<option value="' + d + '">' + d + '</option>')
                        }
                    });
                });
            }
        });
    }

    $(document).ready(function () {

        var $loader = $('#loader');
        $loader.append('<div class="loader"></div>');


        // Get root data
        $.ajax({
            'url': endpoint,
            'data': {},
            'dataType': 'json',
            'success': function (response) {
                $loader.html('');

                if (response.data !== undefined) {
                    if (response.data.length === 0) {
                        $('#message').html(
                            '<div class="alert alert-danger" role="alert">' +
                            'No records found' +
                            '</div>'
                        );
                    }
                    makeBrowseTable(response.data);
                } else {
                    displayObject(response);
                }
            },
            'error': function (jqXHR, textStatus, errorThrown) {
                $loader.html('');
                $('#message').html('Something went wrong: ' + textStatus);
            }
        });
    });
})();