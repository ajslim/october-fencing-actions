(function browse() {
    var url = new URL(window.location);
    var resource = url.searchParams.get("u");
    var endpoint = '/api/' + resource;

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
            "ajax": endpoint,
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