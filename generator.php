<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

session_start();
header('Content-Type: application/json');

$conn = mysqli_connect("localhost", "", "", "");

if (!$conn) {
    $response['message'] = 'Database connection failed: ' . mysqli_connect_error();
    echo json_encode($response);
    exit;
}

$response = ['success' => false];

function parseSQL($sql) {
    $sql = trim($sql);
    $tableMatch = preg_match('/CREATE TABLE\s+`?(\w+)`?/i', $sql, $matches);
    if (!$tableMatch) {
        throw new Exception('Invalid SQL: Table name not found');
    }
    $tableName = $matches[1];

    $columnDefs = array_filter(array_map('trim', explode("\n", $sql)), function($line) {
        return $line && !preg_match('/^CREATE TABLE/i', $line) && !preg_match('/^\)/', $line);
    });

    $columns = [];
    foreach ($columnDefs as $line) {
        if (preg_match('/^`?(\w+)`?\s+([A-Z]+)(\(\d+(,\d+)?\))?(?:\s+(AUTO_INCREMENT|PRIMARY\s+KEY|UNIQUE))?/i', $line, $match)) {
            $isPrimaryKey = preg_match('/PRIMARY\s+KEY|AUTO_INCREMENT/i', $line);
            $columns[] = [
                'name' => $match[1],
                'type' => $match[2],
                'isPrimaryKey' => $isPrimaryKey
            ];
        }
    }

    if (empty($columns)) {
        throw new Exception('Invalid SQL: No valid columns found');
    }

    return ['table' => $tableName, 'columns' => $columns];
}

function getInputType($colType) {
    switch (strtoupper($colType)) {
        case 'INT':
        case 'DECIMAL':
        case 'FLOAT':
            return 'number';
        case 'VARCHAR':
        case 'TEXT':
            return 'text';
        case 'DATE':
            return 'date';
        case 'DATETIME':
            return 'datetime-local';
        default:
            return 'text';
    }
}

function generateHTML($table, $columns, $fieldConfig, $imageField = null, $view = 'table', $columnOrder = []) {
    $tableCap = ucfirst($table);
    $formFields = '';
    $tableHeaders = '';
    $tableCells = '';
    $listViewContent = '';
    $gridViewContent = '';
    $editParams = '';
    $editAssignments = '';
    $validationRules = '';

    // Form columns (non-primary, shown in form)
    $formColumns = array_filter($columns, function($col) use ($fieldConfig) {
        return !$col['isPrimaryKey'] && (!isset($fieldConfig[$col['name']]['showInForm']) || $fieldConfig[$col['name']]['showInForm'] !== false);
    });

    // Ordered columns for views (non-primary, shown in table)
    $nonPrimaryColumns = array_filter($columns, function($col) {
        return !$col['isPrimaryKey'];
    });
    $orderedColumns = !empty($columnOrder) ? 
        array_filter(array_map(function($colName) use ($nonPrimaryColumns, $fieldConfig) {
            $col = array_filter($nonPrimaryColumns, function($c) use ($colName) { return $c['name'] === $colName; });
            $col = reset($col);
            return $col && (!isset($fieldConfig[$col['name']]['showInTable']) || $fieldConfig[$col['name']]['showInTable'] !== false) ? $col : null;
        }, $columnOrder), function($col) { return $col !== null; }) :
        array_filter($nonPrimaryColumns, function($col) use ($fieldConfig) {
            return !isset($fieldConfig[$col['name']]['showInTable']) || $fieldConfig[$col['name']]['showInTable'] !== false;
        });

    foreach ($formColumns as $col) {
        $colName = $col['name'];
        $colCap = implode(' ', array_map('ucfirst', explode('_', $colName)));
        $inputType = getInputType($col['type']);
        $required = isset($fieldConfig[$colName]['required']) && $fieldConfig[$colName]['required'];

        if ($imageField && $colName === $imageField) {
            $formFields .= "
                <div class=\"mt-4\">
                    <label for=\"$colName\" class=\"block text-sm font-medium text-gray-700\">Image</label>
                    <input type=\"file\" id=\"$colName\" name=\"$colName\" accept=\"image/*\" class=\"mt-1 block w-full cursor-pointer border border-gray-300 rounded-lg shadow-sm text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-black transition" . ($required ? ' required' : '') . "\">
                    <img id=\"preview_$colName\" src=\"\" alt=\"Preview\" class=\"mt-3 max-h-48 rounded-xl hidden shadow-md border border-gray-200\">
                    <p id=\"error_$colName\" class=\"text-red-500 text-sm mt-1 hidden\">" . ($required ? "$colCap is required" : "Invalid $colCap") . "</p>
                </div>";
        } else {
            $formFields .= "
                <div class=\"mt-4\">
                    <label for=\"$colName\" class=\"block text-sm font-medium text-gray-700\">$colCap</label>
                    <input type=\"$inputType\" id=\"$colName\" name=\"$colName\" class=\"mt-1 block w-full border border-gray-300 rounded-lg bg-white px-4 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none" . ($required ? ' required' : '') . "\">
                    <p id=\"error_$colName\" class=\"text-red-500 text-sm mt-1 hidden\">" . ($required ? "$colCap is required" : "Invalid $colCap") . "</p>
                </div>";
        }
    }

    foreach ($orderedColumns as $col) {
        $colName = $col['name'];
        $colCap = implode(' ', array_map('ucfirst', explode('_', $colName)));
        $required = isset($fieldConfig[$colName]['required']) && $fieldConfig[$colName]['required'];

        if ($imageField && $colName === $imageField) {
            $tableHeaders = "<th scope=\"col\" class=\"px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider\">Image</th>" . $tableHeaders;
            $tableCells = "<td class=\"px-6 py-4 whitespace-nowrap\"><img src=\"\${data.$colName || 'https://via.placeholder.com/100'}\" alt=\"Image\" class=\"h-[100px] w-[100px] max-w-[100px] rounded-md object-cover border border-gray-200\"></td>" . $tableCells;
            $listViewContent = "<img src=\"\${data.$colName || 'https://via.placeholder.com/96'}\" alt=\"Image\" class=\"h-24 w-24 rounded-md object-cover border border-gray-200\">";
            $gridViewContent = "<div class=\"w-full\"><img src=\"\${data.$colName || 'https://via.placeholder.com/300'}\" alt=\"Image\" class=\"w-full h-48 object-cover rounded-t-xl border border-gray-200\"></div>";
        } else {
            $tableHeaders .= "<th scope=\"col\" class=\"px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider\">$colCap</th>";
            $tableCells .= "<td class=\"px-6 py-4 whitespace-nowrap text-gray-700\">\${data.$colName || '-'}</td>";
            $listViewContent .= "<p class=\"text-sm text-gray-600\">$colCap: \${data.$colName || '-'}</p>";
            $gridViewContent .= "<p class=\"mb-2 text-sm text-gray-600\">$colCap: \${data.$colName || '-'}</p>";
        }

        $editParams .= ", $colName";
        if ($imageField && $colName === $imageField) {
            $editAssignments .= "$('#preview_$colName').attr('src', data.$colName || '').toggle(!!data.$colName);";
        } else {
            $editAssignments .= "$('#$colName').val(data.$colName || '');";
        }

        if ($required) {
            $validationRules .= "
                if (!$('#$colName').val()) {
                    $('#$colName').addClass('border-red-500');
                    $('#error_$colName').removeClass('hidden');
                    isValid = false;
                } else {
                    $('#$colName').removeClass('border-red-500');
                    $('#error_$colName').addClass('hidden');
                }";
        }

        if (stripos($colName, 'email') !== false) {
            $validationRules .= "
                if ($('#$colName').val() && !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/.test($('#$colName').val())) {
                    $('#$colName').addClass('border-red-500');
                    $('#error_$colName').removeClass('hidden').text('Invalid email format');
                    isValid = false;
                }";
        }

        if (stripos($colName, 'phone') !== false) {
            $validationRules .= "
                if ($('#$colName').val() && $('#$colName').val().replace(/\\D/g, '').length < 7) {
                    $('#$colName').addClass('border-red-500');
                    $('#error_$colName').removeClass('hidden').text('Invalid Phone number');
                    isValid = false;
                }";
        }
    }

    $editParams = substr($editParams, 2);
    $imageUploadScript = $imageField ? "
            $('#$imageField').on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        $('#preview_$imageField').attr('src', event.target.result).removeClass('hidden');
                    };
                    reader.readAsDataURL(file);
                }
                $('#$imageField').removeClass('border-red-500');
                $('#error_$imageField').addClass('hidden');
            });" : '';

    $viewContent = '';
    $idHeader = isset($fieldConfig['id']['showInTable']) && $fieldConfig['id']['showInTable'] ? "<th scope=\"col\" class=\"px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider\">ID</th>" : '';
    $idCell = isset($fieldConfig['id']['showInTable']) && $fieldConfig['id']['showInTable'] ? "<td class=\"px-6 py-4 whitespace-nowrap text-gray-700\">\${data.id}</td>" : '';
    $idListContent = isset($fieldConfig['id']['showInTable']) && $fieldConfig['id']['showInTable'] ? "<p class=\"text-sm text-gray-600\">ID: \${data.id}</p>" : '';
    $idGridContent = isset($fieldConfig['id']['showInTable']) && $fieldConfig['id']['showInTable'] ? "<p class=\"mb-2 text-sm text-gray-600\">ID: \${data.id}</p>" : '';

    if ($view === 'table') {
        $viewContent = "
            <table id=\"crudTable\" class=\"min-w-full divide-y divide-gray-200\">
                <thead class=\"bg-gray-50\">
                    <tr>
                        $idHeader
                        $tableHeaders
                        <th scope=\"col\" class=\"px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider\">Actions</th>
                    </tr>
                </thead>
                <tbody class=\"divide-y divide-gray-200\"></tbody>
            </table>";
    } elseif ($view === 'list') {
        $viewContent = "<div id=\"crudTable\" class=\"list-view space-y-4\"></div>";
    } elseif ($view === 'grid' || $view === 'carousel') {
        $viewContent = "<div id=\"crudTable\" class=\"grid-view grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 p-4\"></div>";
    }

    $dataTablesCDN = $view === 'table' ? "
    <link rel=\"stylesheet\" href=\"https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css\">
    <script src=\"https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js\"></script>" : '';

    $carouselScript = $view === 'carousel' ? "
    <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.css\">
    <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick-theme.min.css\">
    <script src=\"https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js\"></script>
    <script>
        $(document).ready(function() {
            $('#crudTable').slick({
                slidesToShow: 3,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 3000,
                responsive: [
                    { breakpoint: 1024, settings: { slidesToShow: 2 } },
                    { breakpoint: 640, settings: { slidesToShow: 1 } }
                ]
            });
        });
    </script>" : '';

    $htmlContent = "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>$tableCap Management</title>
    <script src=\"https://code.jquery.com/jquery-3.7.1.min.js\" integrity=\"sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=\" crossorigin=\"anonymous\"></script>
    <script src=\"https://cdn.jsdelivr.net/npm/sweetalert2@11\"></script>
    <script src=\"https://cdn.tailwindcss.com\"></script>
    <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css\">
    $dataTablesCDN
    $carouselScript
    <style>
        .error-input { border-color: #ef4444 !important; }
        .grid-view, .list-view { padding: 2rem 0; }
        .card { 
            transition: transform 0.3s ease, box-shadow 0.3s ease; 
            padding: 1.5rem; 
            background: white; 
            border-radius: 1rem; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        }
        .card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 12px 24px rgba(0,0,0,0.15); 
        }
        .action-btn {
            padding: 0.5rem;
            font-size: 0.875rem;
            line-height: 1.25rem;
        }
        .cancel-btn {
            border: 2px solid black;
            background: transparent;
            color: black;
        }
        @media (max-width: 768px) { 
            .grid-view { grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
        }
        @media (max-width: 640px) { 
            .grid-view { grid-template-columns: 1fr; }
        }
        .modal { transition: opacity 0.3s ease, visibility 0.3s ease; }
    </style>
</head>
<body class=\"bg-gray-50 min-h-screen font-sans\">
    <div class=\"container mx-auto p-8 max-w-6xl\">
        <div class=\"flex justify-between items-center mb-8\">
            <h1 class=\"text-3xl font-bold text-gray-900\">$tableCap</h1>
            <button type=\"button\" id=\"addRecord\" class=\"bg-black text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition-colors text-sm font-medium\">
                <i class=\"fas fa-plus mr-2\"></i> Add $tableCap
            </button>
        </div>
        <div id=\"tableContainer\" class=\"mt-6\">
            $viewContent
        </div>
        
        <div id=\"crudModal\" class=\"modal fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50 opacity-0 invisible\">
            <div class=\"modal-content bg-white rounded-xl shadow-2xl p-8 w-full max-w-2xl max-h-[80vh] overflow-y-auto\">
                <h2 class=\"text-2xl font-semibold text-gray-800 mb-6\">Add/Edit $tableCap</h2>
                <form id=\"crudForm\" class=\"space-y-6\"" . ($imageField ? ' enctype="multipart/form-data"' : '') . ">
                    <input type=\"hidden\" id=\"id\" name=\"id\">
                    $formFields
                    <div class=\"flex space-x-4 justify-end mt-6\">
                        <button type=\"button\" id=\"cancelEdit\" class=\"cancel-btn px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors text-sm\">Cancel</button>
                        <button type=\"submit\" class=\"bg-black text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition-colors text-sm\">
                            <i class=\"fas fa-save mr-2\"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            const \$crudForm = $('#crudForm');
            const \$crudTable = $('#crudTable');
            const \$tableContainer = $('#tableContainer');
            const \$crudModal = $('#crudModal');
            const \$addRecord = $('#addRecord');
            const \$cancelEdit = $('#cancelEdit');

            " . ($view === 'table' ? "
            $('#crudTable').DataTable({
                responsive: true,
                pageLength: 10,
                language: { emptyTable: 'No records found' }
            });
            " : '') . "

            $imageUploadScript

            function validateForm() {
                let isValid = true;
                $('.error-input').removeClass('border-red-500');
                $('[id^=error_]').addClass('hidden');
                $validationRules
                return isValid;
            }

            function showModal(isEdit = false, data = {}) {
                \$crudForm[0].reset();
                $('#id').val('');
                $('.error-input').removeClass('border-red-500');
                $('[id^=error_]').addClass('hidden');
                " . ($imageField ? "$('#preview_$imageField').addClass('hidden');" : '') . "
                if (isEdit && data) {
                    $('#id').val(data.id);
                    $editAssignments
                }
                \$crudModal.removeClass('opacity-0 invisible').addClass('opacity-100 visible');
            }

            function hideModal() {
                \$crudModal.removeClass('opacity-100 visible').addClass('opacity-0 invisible');
            }

            function fetchRecords() {
                Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                $.ajax({
                    url: '/api/widget/get/json/$table-api',
                    type: 'POST',
                    data: { action: 'get' },
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            " . ($view === 'table' ? "\$crudTable.DataTable().clear().destroy();" : "\$crudTable.empty();") . "
                            if (response.data.length === 0) {
                                \$tableContainer.hide();
                                " . ($view === 'table' ? "$('#crudTable').DataTable({ responsive: true, pageLength: 10, language: { emptyTable: 'No records found' } });" : '') . "
                                return;
                            }
                            \$tableContainer.show();
                            response.data.forEach(function(data) {
                                const row = " . ($view === 'table' ? "
                                    `<tr class=\"hover:bg-gray-50 transition\">
                                        $idCell
                                        $tableCells
                                        <td class=\"px-6 py-4 whitespace-nowrap space-x-2\">
                                            <button type=\"button\" onclick=\"editRecord(\${data.id})\" class=\"action-btn text-white bg-black px-2 py-1 rounded-md hover:bg-gray-800 transition-colors text-sm\"><i class=\"fas fa-edit\"></i></button>
                                            <button type=\"button\" onclick=\"deleteRecord(\${data.id})\" class=\"action-btn text-white bg-black px-2 py-1 rounded-md hover:bg-gray-800 transition-colors text-sm\"><i class=\"fas fa-trash\"></i></button>
                                        </td>
                                    </tr>`
                                " : ($view === 'list' ? "
                                    `<div class=\"card flex items-center justify-between border border-gray-200 bg-white rounded-xl p-6 hover:bg-gray-50 transition\">
                                        <div class=\"flex items-center gap-6\">
                                            $listViewContent
                                            <div class=\"space-y-2\">
                                                $idListContent
                                                $listViewContent
                                            </div>
                                        </div>
                                        <div class=\"flex gap-2\">
                                            <button type=\"button\" onclick=\"editRecord(\${data.id})\" class=\"action-btn text-white bg-black px-2 py-1 rounded-md hover:bg-gray-800 transition-colors text-sm\"><i class=\"fas fa-edit\"></i></button>
                                            <button type=\"button\" onclick=\"deleteRecord(\${data.id})\" class=\"action-btn text-white bg-black px-2 py-1 rounded-md hover:bg-gray-800 transition-colors text-sm\"><i class=\"fas fa-trash\"></i></button>
                                        </div>
                                    </div>`
                                " : "
                                    `<div class=\"card rounded-xl shadow-md overflow-hidden bg-white\">
                                        $gridViewContent
                                        $idGridContent
                                        <div class=\"space-x-2 mt-4 p-4 bg-gray-50 rounded-b-xl\">
                                            <button type=\"button\" onclick=\"editRecord(\${data.id})\" class=\"action-btn text-white bg-black px-2 py-1 rounded-md hover:bg-gray-800 transition-colors text-sm\"><i class=\"fas fa-edit\"></i></button>
                                            <button type=\"button\" onclick=\"deleteRecord(\${data.id})\" class=\"action-btn text-white bg-black px-2 py-1 rounded-md hover:bg-gray-800 transition-colors text-sm\"><i class=\"fas fa-trash\"></i></button>
                                        </div>
                                    </div>`
                                ")) . "
                                \$crudTable.append(row);
                            });
                            " . ($view === 'table' ? "$('#crudTable').DataTable({ responsive: true, pageLength: 10, language: { emptyTable: 'No records found' } });" : '') . "
                            " . ($view === 'carousel' ? "$('#crudTable').slick('refresh');" : '') . "
                        } else {
                            \$tableContainer.hide();
                            " . ($view === 'table' ? "$('#crudTable').DataTable({ responsive: true, pageLength: 10, language: { emptyTable: 'No records found' } });" : '') . "
                            Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        \$tableContainer.hide();
                        " . ($view === 'table' ? "$('#crudTable').DataTable({ responsive: true, pageLength: 10, language: { emptyTable: 'No records found' } });" : '') . "
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error fetching records: ' + error });
                    }
                });
            }

            \$crudForm.on('submit', function(e) {
                e.preventDefault();
                if (!validateForm()) {
                    Swal.fire({ icon: 'error', title: 'Validation Error', text: 'Please correct the errors in the form' });
                    return;
                }

                Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                const formData = new FormData(this);
                formData.append('action', 'upsert');
                " . ($imageField ? "if ($('#$imageField').get(0).files.length > 0) { formData.append('has_image', '1'); }" : '') . "
                $.ajax({
                    url: '/api/widget/post/json/$table-api',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success) {
                            \$crudForm[0].reset();
                            $('#id').val('');
                            $('.error-input').removeClass('border-red-500');
                            $('[id^=error_]').addClass('hidden');
                            " . ($imageField ? "$('#preview_$imageField').addClass('hidden');" : '') . "
                            hideModal();
                            fetchRecords();
                            Swal.fire({ icon: 'success', title: 'Success', text: 'Operation successful!' });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error: ' + error });
                    }
                });
            });

            \$crudForm.on('keyup change', 'input, select, textarea', function() {
                const \$input = $(this);
                const id = \$input.attr('id');
                if (id && \$input.val()) {
                    \$input.removeClass('border-red-500');
                    $('#error_' + id).addClass('hidden');
                }
            });

            \$addRecord.on('click', function() {
                showModal();
            });

            window.editRecord = function(id) {
                Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                $.ajax({
                    url: '/api/widget/get/json/$table-api',
                    type: 'POST',
                    data: { action: 'get_single', id: id },
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        if (response.success && response.data) {
                            showModal(true, response.data);
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'Failed to fetch record' });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Error fetching record: ' + error });
                    }
                });
            };

            \$cancelEdit.on('click', function() {
                hideModal();
            });

            window.deleteRecord = function(id) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You won\'t be able to revert this!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                        $.ajax({
                            url: '/api/widget/post/json/$table-api',
                            type: 'POST',
                            data: { action: 'delete', id: id },
                            dataType: 'json',
                            success: function(response) {
                                Swal.close();
                                if (response.success) {
                                    fetchRecords();
                                    Swal.fire({ icon: 'success', title: 'Deleted!', text: 'Record deleted successfully!' });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                                }
                            },
                            error: function(xhr, status, error) {
                                Swal.close();
                                Swal.fire({ icon: 'error', title: 'Error', text: 'Error: ' + error });
                            }
                        });
                    }
                });
            };

            fetchRecords();
        });
    </script>
</body>
</html>";
    return $htmlContent;
}

function generatePHP($table, $columns, $fieldConfig, $imageField = null) {
    $insertFields = implode(', ', array_map(function($col) { return $col['name']; }, array_filter($columns, function($col) use ($fieldConfig) {
        return !$col['isPrimaryKey'] && (!isset($fieldConfig[$col['name']]['showInForm']) || $fieldConfig[$col['name']]['showInForm'] !== false);
    })));
    $insertValues = implode(', ', array_map(function($col) { return "'\${$col['name']}'"; }, array_filter($columns, function($col) use ($fieldConfig) {
        return !$col['isPrimaryKey'] && (!isset($fieldConfig[$col['name']]['showInForm']) || $fieldConfig[$col['name']]['showInForm'] !== false);
    })));
    $updateAssignments = implode(', ', array_map(function($col) { return "{$col['name']} = '\${$col['name']}'"; }, array_filter($columns, function($col) use ($fieldConfig) {
        return !$col['isPrimaryKey'] && (!isset($fieldConfig[$col['name']]['showInForm']) || $fieldConfig[$col['name']]['showInForm'] !== false);
    })));
    $postVars = implode("\n    ", array_map(function($col) use ($imageField) {
        return "\${$col['name']} = isset(\$_POST['{$col['name']}']) ? addslashes(\$_POST['{$col['name']}']) : '';";
    }, array_filter($columns, function($col) use ($fieldConfig, $imageField) {
        return !$col['isPrimaryKey'] && $col['name'] !== $imageField && (!isset($fieldConfig[$col['name']]['showInForm']) || $fieldConfig[$col['name']]['showInForm'] !== false);
    })));
    
    $validationCode = '';
    foreach ($columns as $col) {
        $colName = $col['name'];
        if (isset($fieldConfig[$colName]['required']) && $fieldConfig[$colName]['required'] && !$col['isPrimaryKey']) {
            $validationCode .= "
        if (empty(\${$colName})) {
            \$response['message'] = '" . implode(' ', array_map('ucfirst', explode('_', $colName))) . " is required';
            echo json_encode(\$response);
            exit;
        }";
        }
        if (stripos($colName, 'email') !== false) {
            $validationCode .= "
        if (!empty(\${$colName}) && !filter_var(\${$colName}, FILTER_VALIDATE_EMAIL)) {
            \$response['message'] = 'Invalid email format';
            echo json_encode(\$response);
            exit;
        }";
        }
        if (stripos($colName, 'phone') !== false) {
            $validationCode .= "
        if (empty(\${$colName})) {
            \$response['message'] = 'Invalid Phone number';
            echo json_encode(\$response);
            exit;
        }";
        }
    }

    $imageHandling = $imageField ? "
    \${$imageField} = '';
    if (isset(\$_FILES['$imageField']) && \$_FILES['$imageField']['error'] === UPLOAD_ERR_OK) {
        \$upload_dir = 'Uploads/';
        if (!file_exists(\$upload_dir)) mkdir(\$upload_dir, 0777, true);
        \$file_name = time() . '_' . basename(\$_FILES['$imageField']['name']);
        \$file_path = \$upload_dir . \$file_name;
        if (move_uploaded_file(\$_FILES['$imageField']['tmp_name'], \$file_path)) {
            \${$imageField} = '/' . \$file_path;
        }
    }" : '';

    $phpContent = "<?php
header('Content-Type: application/json');

\$response = ['success' => false];

\$action = isset(\$_POST['action']) ? \$_POST['action'] : '';
\$db = brilliantDirectories::getDatabaseConfiguration('database');
\$table = '$table';

switch (\$action) {
    case 'get':
        \$result = mysql(\$db, \"SELECT * FROM \$table\");
        \$records = [];
        while (\$row = mysql_fetch_assoc(\$result)) {
            \$records[] = \$row;
        }
        \$response['success'] = true;
        \$response['data'] = \$records;
        echo json_encode(\$response);
        break;

    case 'get_single':
        \$id = isset(\$_POST['id']) ? addslashes(\$_POST['id']) : '';
        if (\$id) {
            \$result = mysql(\$db, \"SELECT * FROM \$table WHERE id = '\$id'\");
            \$row = mysql_fetch_assoc(\$result);
            if (\$row) {
                \$response['success'] = true;
                \$response['data'] = \$row;
            } else {
                \$response['message'] = 'Record not found';
            }
        } else {
            \$response['message'] = 'Invalid ID';
        }
        echo json_encode(\$response);
        break;

    case 'upsert':
        \$id = isset(\$_POST['id']) ? addslashes(\$_POST['id']) : '';
        $imageHandling
        $postVars
        $validationCode
        if (" . ($insertFields ? implode(' || ', array_map(function($col) { return "\${$col['name']} != ''"; }, array_filter($columns, function($col) use ($fieldConfig) { return !$col['isPrimaryKey'] && (!isset($fieldConfig[$col['name']]['showInForm']) || $fieldConfig[$col['name']]['showInForm'] !== false); }))) : 'true') . ") {
            if (\$id) {
                \$sql = \"UPDATE \$table SET $updateAssignments WHERE id = '\$id'\";
                mysql(\$db, \$sql);
                \$response['success'] = true;
                \$response['id'] = \$id;
            } else {
                \$sql = \"INSERT INTO \$table ($insertFields) VALUES ($insertValues)\";
                mysql(\$db, \$sql);
                \$insert_id = mysql_insert_id();
                \$response['success'] = true;
                \$response['id'] = \$insert_id;
            }
        } else {
            \$response['message'] = 'Missing required fields.';
        }
        echo json_encode(\$response);
        break;

    case 'delete':
        \$id = isset(\$_POST['id']) ? addslashes(\$_POST['id']) : '';
        if (\$id) {
            mysql(\$db, \"DELETE FROM \$table WHERE id = '\$id'\");
            \$response['success'] = true;
        } else {
            \$response['message'] = 'Invalid ID.';
        }
        echo json_encode(\$response);
        break;

    default:
        \$response['message'] = 'Invalid action.';
        echo json_encode(\$response);
}
?>";
    return $phpContent;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'parse_sql':
        try {
            $sql = isset($_POST['sql']) ? trim($_POST['sql']) : '';
            if (empty($sql)) {
                throw new Exception('SQL input is empty');
            }
            $response['success'] = true;
            $response['data'] = parseSQL($sql);
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        break;

    case 'generate':
        try {
            $table = isset($_POST['table']) ? trim($_POST['table']) : '';
            $columns = isset($_POST['columns']) ? json_decode($_POST['columns'], true) : [];
            $fieldConfig = isset($_POST['fieldConfig']) ? json_decode($_POST['fieldConfig'], true) : [];
            $imageField = isset($_POST['imageField']) && $_POST['imageField'] !== '' ? $_POST['imageField'] : null;
            $view = isset($_POST['view']) ? $_POST['view'] : 'table';
            $columnOrder = isset($_POST['columnOrder']) ? json_decode($_POST['columnOrder'], true) : [];
            $sql = isset($_POST['sql']) ? trim($_POST['sql']) : '';

            if (empty($table) || empty($columns)) {
                throw new Exception('Invalid input data');
            }

            // Generate HTML and PHP content
            $generated_html = generateHTML($table, $columns, $fieldConfig, $imageField, $view, $columnOrder);
            $generated_php = generatePHP($table, $columns, $fieldConfig, $imageField);

            // Save project details to the projects table using MySQLi
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $fieldConfigJson = json_encode($fieldConfig);
            $columnOrderJson = json_encode($columnOrder);
            $created_at = date('Y-m-d H:i:s');

            $sql_query = mysqli_real_escape_string($conn, $sql);
            $table_name = mysqli_real_escape_string($conn, $table);
            $field_config = mysqli_real_escape_string($conn, $fieldConfigJson);
            $image_field = $imageField ? mysqli_real_escape_string($conn, $imageField) : '';
            $view_type = mysqli_real_escape_string($conn, $view);
            $column_order = mysqli_real_escape_string($conn, $columnOrderJson);
            $generated_html_escaped = mysqli_real_escape_string($conn, $generated_html);
            $generated_php_escaped = mysqli_real_escape_string($conn, $generated_php);
            $ip = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR']);
            $created_at = date('Y-m-d H:i:s');
            
           $token = generateToken();
            
           $query = "INSERT INTO projects (
                user_id, sql_query, table_name, field_config, image_field, view_type, column_order, created_at, generated_html, generated_php, ip, token
            ) VALUES (
                '$user_id', '$sql_query', '$table_name', '$field_config', '$image_field', '$view_type', '$column_order', '$created_at', '$generated_html_escaped', '$generated_php_escaped', '$ip', '$token'
            )";
            if (!mysqli_query($conn, $query)) {
                throw new Exception('Failed to save project: ' . mysqli_error($conn));
            }
            $project_id = mysqli_insert_id($conn);

            sleep(3); // Keep the original delay
            $response['success'] = true;
            $response['data'] = [
                'html' => $generated_html,
                'php' => $generated_php,
                'token' => $token
            ];

        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        break;

    case 'save_project':
        try {
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $sql = isset($_POST['sql']) ? trim($_POST['sql']) : '';
            $table = isset($_POST['table']) ? trim($_POST['table']) : '';
            $columns = isset($_POST['columns']) ? json_decode($_POST['columns'], true) : [];
            $fieldConfig = isset($_POST['fieldConfig']) ? json_decode($_POST['fieldConfig'], true) : [];
            $imageField = isset($_POST['imageField']) && $_POST['imageField'] !== '' ? $_POST['imageField'] : null;
            $view = isset($_POST['view']) ? $_POST['view'] : 'table';
            $columnOrder = isset($_POST['columnOrder']) ? json_decode($_POST['columnOrder'], true) : [];

            if (empty($table) || empty($columns)) {
                throw new Exception('Invalid input data');
            }

            // Generate HTML and PHP content
            $generated_html = generateHTML($table, $columns, $fieldConfig, $imageField, $view, $columnOrder);
            $generated_php = generatePHP($table, $columns, $fieldConfig, $imageField);

            $fieldConfigJson = json_encode($fieldConfig);
            $columnOrderJson = json_encode($columnOrder);
            $created_at = date('Y-m-d H:i:s');

            $sql_query = mysqli_real_escape_string($conn, $sql);
            $table_name = mysqli_real_escape_string($conn, $table);
            $field_config = mysqli_real_escape_string($conn, $fieldConfigJson);
            $image_field = $imageField ? mysqli_real_escape_string($conn, $imageField) : '';
            $view_type = mysqli_real_escape_string($conn, $view);
            $column_order = mysqli_real_escape_string($conn, $columnOrderJson);
            $generated_html_escaped = mysqli_real_escape_string($conn, $generated_html);
            $generated_php_escaped = mysqli_real_escape_string($conn, $generated_php);
            $created_at = mysqli_real_escape_string($conn, $created_at);
            $token = generateToken();
            
           $query = "INSERT INTO projects (
                user_id, sql_query, table_name, field_config, image_field, view_type, column_order, created_at, generated_html, generated_php, ip, token
            ) VALUES (
                '$user_id', '$sql_query', '$table_name', '$field_config', '$image_field', '$view_type', '$column_order', '$created_at', '$generated_html_escaped', '$generated_php_escaped', '$ip', '$token'
            )";
            if (!mysqli_query($conn, $query)) {
                throw new Exception('Failed to save project: ' . mysqli_error($conn));
            }
            $project_id = mysqli_insert_id($conn);

            $response['success'] = true;
            $response['project_id'] = $project_id;
            $response['data'] = [
                'html' => $generated_html,
                'php' => $generated_php,
                'token' => $token
            ];

            $response['message'] = 'Project saved successfully';
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }
        break;

    default:
        $response['message'] = 'Invalid action';
        break;
}
function generateToken() {
    return bin2hex(random_bytes(2)) . '-' . 
           bin2hex(random_bytes(2)) . '-' . 
           bin2hex(random_bytes(2)) . '-' . 
           bin2hex(random_bytes(2));
}

echo json_encode($response);
?>