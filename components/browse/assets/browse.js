(function browse() {
    var url = new URL(window.location);
    var endpoint = '/api/' + url.searchParams.get("u");

    function displayObject(data) {
        var $display = $('#display');

        Object.keys(data).forEach(function (key, index) {
            if (key === 'children') {
                data[key].forEach(function (child) {
                    $display.append('<div><a href="' + window.location + '/' + child + '">' + child + '</a></div>');
                });
            } else {
                $display.append('<div><span>' + key + '</span> : <span>' + data[key] + '</span>');
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

            if (key === 'thumb' || key === 'photo_url') {
                column.render = function (data, type, row) {
                    return '<img width="120" src="' + data + '" />';
                }
            }

            if (key === 'confidence' || key === 'consensus') {
                column.render = function (data, type, row) {
                    return Math.floor(data * 100) + '%';
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
            "ajax": endpoint,
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

        $list.on('click', 'tr', function () {
            var data = $table.row(this).data();
            if (data.link !== undefined) {
                window.location = window.location = data.link
            } else {
                window.location = window.location + '/' + data.id;
            }
        })
    }

    $(document).ready(function () {

        // Get root data
        $.ajax({
            'url': endpoint,
            'data': {},
            'dataType': 'json',
            'success': function (response) {
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
            }
        });
    });
})();