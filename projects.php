<?php
// Parse token from URL path
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $path);
$token = isset($parts[1]) ? $parts[1] : ''; // because URL is /projects/{token}

// Fetch project if token exists
$projectData = null;
if ($token) {
    $conn = mysqli_connect("localhost", "", "", "");
    $tokenSafe = mysqli_real_escape_string($conn, $token);
    $res = mysqli_query($conn, "SELECT * FROM projects WHERE token='$tokenSafe' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $projectData = mysqli_fetch_assoc($res);
    }
}
?>
<script>
window.projectData = <?= json_encode($projectData ?? null) ?>;
</script>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="https://mforfiaz.com/logo.png"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/classic.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Open+Sans:wght@400;600&family=Poppins:wght@400;500&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { accent-color: black; }
        .copy-btn { transition: all 0.3s ease; }
        .copy-icon { display: inline-block; }
        .check-icon { display: none; color: #10B981; }
        .copied .copy-icon { display: none; }
        .copied .check-icon { display: inline-block; }
        .modal { transition: opacity 0.3s ease, visibility 0.3s ease; }
        .image-field-select { background: linear-gradient(to right, #f0f9ff, #e0f2fe); padding: 1rem; border-radius: 0.5rem; }
        .wizard-step { display: none; }
        .wizard-step.active { display: block; }
        .step-indicator { display: flex; gap: 0.5rem; align-items: center; flex-wrap: nowrap; }
        .step-circle { width: 1.5rem; height: 1.5rem; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600; color: #6b7280; flex-shrink: 0; }
        .step-circle.active { background: black; color: white; }
        .step-line { flex: 1; height: 2px; background: #e5e7eb; min-width: 1rem; }
        .step-line.active { background: black; }
        .error-input { border-color: #ef4444 !important; }
        .sortable-column, .sortable-form-field { cursor: move; padding: 10px; border-radius: 8px; background: #f8f9fa; }
        .sortable-column:hover, .sortable-form-field:hover { background: #e5e7eb; }
        .action-btn { padding: 0.5rem; font-size: 0.875rem; line-height: 1.25rem; }
        .cancel-btn { border: 2px solid black; background: transparent; color: black; }
        .widget-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        .pickr-container { display: inline-block; }
        .customization-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        @media (max-width: 640px) {
            .container { padding-left: 1rem; padding-right: 1rem; }
            .modal-content { width: 95%; }
            .field-config-grid { grid-template-columns: 1fr; }
            .step-indicator { gap: 0.25rem; justify-content: center; }
            .step-circle { width: 1.25rem; height: 1.25rem; font-size: 0.65rem; }
            .step-line { min-width: 0.5rem; }
            .customization-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-sans">
     <nav class="bg-white shadow-md fixed w-full top-0 left-0 z-50">
        <div class="max-w-6xl mx-auto px-6 py-4 flex justify-between items-center">
            <a href="https://mforfiaz.com/" class="flex items-center space-x-3">
                <img src="https://mforfiaz.com/logo.png" alt="Logo" class="h-12"> 
                <span class="text-2xl font-bold">Mforfiaz</span>
            </a>
            <button id="menu-btn" class="block md:hidden focus:outline-none">
                <svg class="w-8 h-8 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                </svg>
            </button>
            <ul id="menu" class="hidden md:flex space-x-6 text-lg">
                <li disabled><a href="https://mforfiaz.com/" class="hover:text-blue-500 transition">Home</a></li>
                <li disabled><a href="https://crudgen.mforfiaz.com/" class="hover:text-blue-500 transition">Projects</a></li>
            </ul>
        </div>
    </nav><br><br><br><br><br>
    <div class="container mx-auto py-8 px-6 max-w-5xl">
        <div class="bg-white rounded-xl shadow-lg p-8">
           <h1 class="text-3xl font-bold text-gray-900 mb-3">CRUD Generator</h1>
<p class="text-gray-600 mb-4">
    Enter your SQL table structure to generate fully customizable CRUD files with jQuery AJAX and Brilliant Directories queries.
</p>

<p class="text-sm text-gray-500">
    Tip: <code>SHOW CREATE TABLE your_table;</code>
</p>
<br>
            <textarea id="sqlInput" class="w-full h-48 p-4 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-6 resize-y bg-gray-50 text-sm" placeholder="e.g., CREATE TABLE students (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), age INT, grade FLOAT);">
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    image VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active','inactive') DEFAULT 'active'
);
            </textarea>
            
            <button id="generateBtn" class="bg-black text-white px-6 py-3 rounded-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex items-center transition-colors">
                <span id="btnText">Generate Files</span>
                <svg id="loadingSpinner" class="hidden animate-spin ml-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>

            <!-- Configuration Modal -->
            <div id="configModal" class="modal fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50 opacity-0 invisible">
                <div class="modal-content bg-white rounded-xl shadow-2xl p-8 w-full max-w-3xl max-h-[80vh] overflow-y-auto">
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Configure CRUD Settings</h3>
                    
                    <!-- Step Indicator -->
                    <div class="step-indicator mb-6 flex justify-center">
                        <div class="step-circle active">1</div>
                        <div class="step-line"></div>
                        <div class="step-circle">2</div>
                        <div class="step-line"></div>
                        <div class="step-circle">3</div>
                        <div class="step-line"></div>
                        <div class="step-circle">4</div>
                        <div class="step-line"></div>
                        <div class="step-circle">5</div>
                    </div>

                    <!-- Step 1: Field Configuration -->
                    <div id="step1" class="wizard-step active">
                        <h4 class="font-semibold text-gray-800 mb-3">Field Configuration</h4>
                        <div id="fieldList" class="field-config-grid grid grid-cols-2 gap-4"></div>
                    </div>

                    <!-- Step 2: Image Field Configuration -->
                    <div id="step2" class="wizard-step">
                        <h4 class="font-semibold text-gray-800 mb-3">Image Field Configuration</h4>
                        <div class="image-field-select p-4 rounded-lg">
                            <label class="inline-flex items-center">
                                <input type="checkbox" id="hasImageField" class="form-checkbox h-5 w-5 text-blue-600 rounded">
                                <span class="ml-2 text-sm text-gray-700">This table has an image field</span>
                            </label>
                            <div id="imageFieldContainer" class="mt-3 hidden">
                                <select id="imageFieldSelect" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2">
                                    <option value="">Select image field</option>
                                </select>
                                <p id="error_imageField" class="text-red-500 text-sm mt-1 hidden">Please select an image field</p>
                                <div class="mt-3">
                                    <label for="imageFolder" class="block text-sm font-medium text-gray-700">Image Upload Folder</label>
                                    <input type="text" id="imageFolder" value="/uploads" class="mt-1 block w-full border border-gray-300 rounded-lg bg-white px-4 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                    <p id="error_imageFolder" class="text-red-500 text-sm mt-1 hidden">Folder path must start with a slash and contain only letters, numbers, and slashes</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: View Configuration -->
                    <div id="step3" class="wizard-step">
                        <h4 class="font-semibold text-gray-800 mb-3">View Configuration</h4>
                        <select id="viewSelect" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mb-4">
                            <option value="table">Table View</option>
                            <option value="list">List View</option>
                            <option value="grid">Grid View</option>
                            <option value="carousel">Carousel View</option>
                        </select>
                        <div id="tableOptions" class="hidden">
                            <h5 class="font-semibold text-gray-700 mb-2">Table Options</h5>
                            <label class="inline-flex items-center mb-2">
                                <input type="checkbox" id="useDataTable" class="form-checkbox h-4 w-4 text-blue-600 rounded" checked>
                                <span class="ml-2 text-sm text-gray-700">Use DataTable (with sorting/filtering)</span>
                            </label>
                            <label class="inline-flex items-center mb-2">
                                <input type="checkbox" id="tableSearch" class="form-checkbox h-4 w-4 text-blue-600 rounded" checked>
                                <span class="ml-2 text-sm text-gray-700">Enable Search</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" id="tablePagination" class="form-checkbox h-4 w-4 text-blue-600 rounded" checked>
                                <span class="ml-2 text-sm text-gray-700">Enable Pagination</span>
                            </label>
                            <div class="mt-3">
                                <label for="tableRowHeight" class="block text-sm font-medium text-gray-700">Row Height</label>
                                <select id="tableRowHeight" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="sm">Small</option>
                                    <option value="md" selected>Medium</option>
                                    <option value="lg">Large</option>
                                </select>
                            </div>
                        </div>
                        <div id="listGridOptions" class="hidden">
                            <h5 class="font-semibold text-gray-700 mb-2">List/Grid Options</h5>
                            <label class="inline-flex items-center mb-2">
                                <input type="checkbox" id="listGridSearch" class="form-checkbox h-4 w-4 text-blue-600 rounded" checked>
                                <span class="ml-2 text-sm text-gray-700">Enable Search</span>
                            </label>
                            <label class="inline-flex items-center mb-2">
                                <input type="checkbox" id="listGridPagination" class="form-checkbox h-4 w-4 text-blue-600 rounded" checked>
                                <span class="ml-2 text-sm text-gray-700">Enable Pagination</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" id="featuredStyle" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                <span class="ml-2 text-sm text-gray-700">Enable Featured Style (larger cards, borders)</span>
                            </label>
                            <div class="mt-3">
                                <label for="cardSpacing" class="block text-sm font-medium text-gray-700">Card Spacing</label>
                                <select id="cardSpacing" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="sm">Small</option>
                                    <option value="md" selected>Medium</option>
                                    <option value="lg">Large</option>
                                </select>
                            </div>
                        </div>
                        <div id="carouselOptions" class="hidden">
                            <h5 class="font-semibold text-gray-700 mb-2">Carousel Options</h5>
                            <label class="inline-flex items-center mb-2">
                                <input type="checkbox" id="carouselAutoplay" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                <span class="ml-2 text-sm text-gray-700">Enable Autoplay</span>
                            </label>
                            <label class="inline-flex items-center mb-2">
                                <input type="checkbox" id="carouselNav" class="form-checkbox h-4 w-4 text-blue-600 rounded" checked>
                                <span class="ml-2 text-sm text-gray-700">Show Navigation Arrows</span>
                            </label>
                            <label class="inline-flex items-center mb-2">
                                <input type="checkbox" id="carouselDots" class="form-checkbox h-4 w-4 text-blue-600 rounded" checked>
                                <span class="ml-2 text-sm text-gray-700">Show Pagination Dots</span>
                            </label>
                            <div class="mt-3">
                                <label for="carouselItems" class="block text-sm font-medium text-gray-700">Slides per Page</label>
                                <input type="number" id="carouselItems" value="3" min="1" max="10" class="mt-1 block w-full border border-gray-300 rounded-lg bg-white px-4 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                                <p id="error_carouselItems" class="text-red-500 text-sm mt-1 hidden">Slides per page must be between 1 and 10</p>
                            </div>
                            <div class="mt-3">
                                <label for="carouselSpeed" class="block text-sm font-medium text-gray-700">Transition Speed (ms)</label>
                                <input type="number" id="carouselSpeed" value="300" min="100" max="2000" class="mt-1 block w-full border border-gray-300 rounded-lg bg-white px-4 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Column and Form Field Order -->
                    <div id="step4" class="wizard-step">
                        <h4 class="font-semibold text-gray-800 mb-3">Sort Columns and Form Fields</h4>
                        <label class="inline-flex items-center mb-3">
                            <input type="checkbox" id="sameOrder" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                            <span class="ml-2 text-sm text-gray-700">Use same order for form and view</span>
                        </label>
                        <div id="viewColumnsSection">
                            <h5 class="font-semibold text-gray-700 mb-2">View Columns Order</h5>
                            <div id="sortableColumns" class="space-y-2"></div>
                        </div>
                        <div id="formFieldsSection" class="mt-4">
                            <h5 class="font-semibold text-gray-700 mb-2">Form Fields Order</h5>
                            <div id="sortableFormFields" class="space-y-2"></div>
                        </div>
                    </div>

                    <!-- Step 5: Theme Customization -->
                    <div id="step5" class="wizard-step">
                        <h4 class="font-semibold text-gray-800 mb-3">Theme Customization</h4>
                        <div class="customization-grid">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Primary Color</label>
                                <div id="primaryColorPicker" class="pickr-container mt-2"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Secondary Color</label>
                                <div id="secondaryColorPicker" class="pickr-container mt-2"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Body Background Color</label>
                                <div id="bodyBgColorPicker" class="pickr-container mt-2"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Card Background Color</label>
                                <div id="cardBgColorPicker" class="pickr-container mt-2"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Text Color</label>
                                <div id="textColorPicker" class="pickr-container mt-2"></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Input Border Radius</label>
                                <select id="inputBorderRadius" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="none">None</option>
                                    <option value="sm">Small</option>
                                    <option value="md" selected>Medium</option>
                                    <option value="lg">Large</option>
                                    <option value="full">Full</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Button Style</label>
                                <select id="buttonStyle" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="square">Square</option>
                                    <option value="rounded" selected>Rounded</option>
                                    <option value="pill">Pill</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Button Size</label>
                                <select id="buttonSize" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="sm">Small</option>
                                    <option value="md" selected>Medium</option>
                                    <option value="lg">Large</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Image Border Style</label>
                                <select id="imageBorderStyle" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="none">None</option>
                                    <option value="sm">Small</option>
                                    <option value="md" selected>Medium</option>
                                    <option value="lg">Large</option>
                                    <option value="full">Full</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Card Shadow</label>
                                <select id="cardShadow" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="none">None</option>
                                    <option value="sm">Small</option>
                                    <option value="md" selected>Medium</option>
                                    <option value="lg">Large</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Card Border Style</label>
                                <select id="cardBorderStyle" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="none">None</option>
                                    <option value="sm">Small</option>
                                    <option value="md" selected>Medium</option>
                                    <option value="lg">Large</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Font Family</label>
                                <select id="fontFamily" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="Roboto">Roboto</option>
                                    <option value="Open Sans">Open Sans</option>
                                    <option value="Poppins">Poppins</option>
                                    <option value="Inter" selected>Inter</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Hover Effect</label>
                                <select id="hoverEffect" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="none">None</option>
                                    <option value="scale" selected>Scale</option>
                                    <option value="shadow">Shadow</option>
                                    <option value="opacity">Opacity</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Padding</label>
                                <select id="padding" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="sm">Small</option>
                                    <option value="md" selected>Medium</option>
                                    <option value="lg">Large</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Margin</label>
                                <select id="margin" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="sm">Small</option>
                                    <option value="md" selected>Medium</option>
                                    <option value="lg">Large</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Gap (View-Specific)</label>
                                <select id="gap" class="block w-full border-gray-200 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm p-2 mt-1">
                                    <option value="sm">Small</option>
                                    <option value="md" selected>Medium</option>
                                    <option value="lg">Large</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="flex justify-between mt-6">
                        <button id="prevStep" class="px-5 py-2 cancel-btn rounded-lg hover:bg-gray-100 transition-colors hidden">Back</button>
                        <div class="flex space-x-4">
                            <button id="cancelConfig" class="px-5 py-2 cancel-btn rounded-lg hover:bg-gray-100 transition-colors">Cancel</button>
                            <button id="nextStep" class="px-5 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition-colors">Next</button>
                            <button id="confirmConfig" class="px-5 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition-colors hidden">Generate Files</button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="projectLinkSection" class="hidden mt-6 p-4 bg-green-50 border border-green-200 rounded-lg flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-green-800 text-sm font-medium mb-1">🎉 Your project is ready!</p>
                    <p id="projectUrlText" class="text-green-900 font-mono text-sm"></p>
                </div>
                <div class="flex gap-2">
                    <button id="copyProjectLink" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                        📋 Copy Link
                    </button>
                    <a id="viewProjectLink" href="#" target="_blank" class="bg-black hover:bg-gray-800 text-white px-4 py-2 rounded-lg text-sm">
                        👁 View Now
                    </a>
                </div>
            </div>

            <div id="resultsSection" class="hidden mt-10 space-y-10">
                <div class="relative">
                    <div class="widget-header mb-3">
                        <h3 id="htmlWidgetTitle" class="text-xl font-semibold text-gray-900"></h3>
                        <button onclick="copyToClipboard('htmlOutput')" class="copy-btn flex items-center text-sm text-blue-600 hover:text-blue-800">
                            <span class="copy-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                            </span>
                            <span class="check-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                        </button>
                    </div>
                    <pre id="htmlOutput" class="bg-gray-50 p-6 rounded-lg overflow-x-auto text-sm font-mono border border-gray-200 max-h-[500px] overflow-y-scroll"></pre>
                </div>

                <div class="relative">
                    <div class="widget-header mb-3">
                        <h3 id="phpWidgetTitle" class="text-xl font-semibold text-gray-900"></h3>
                        <button onclick="copyToClipboard('phpOutput')" class="copy-btn flex items-center text-sm text-blue-600 hover:text-blue-800">
                            <span class="copy-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                            </span>
                            <span class="check-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                        </button>
                    </div>
                    <pre id="phpOutput" class="bg-gray-50 p-6 rounded-lg overflow-x-auto text-sm font-mono border border-gray-200 max-h-[500px] overflow-y-scroll"></pre>
                </div>
            </div>
        </div>
    </div>
    <footer class="text-center text-sm text-gray-500 py-4">
        © <?= date("Y") ?> All rights reserved. Developed by Fiaz Zafar.
    </footer>

    <script>
         const colorPickers = {};
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent;
    navigator.clipboard.writeText(text).then(() => {
        const btn = element.previousElementSibling.querySelector('.copy-btn');
        btn.classList.add('copied');
        setTimeout(() => btn.classList.remove('copied'), 2000);
    });
}

function showConfigModal(columns, previousConfig = {}, previousImageField = null, previousHasImageField = false, previousView = 'table', previousColumnOrder = [], previousFormFieldOrder = [], previousViewOptions = {}, previousThemeOptions = {}) {
    const modal = document.getElementById('configModal');
    const fieldList = document.getElementById('fieldList');
    const imageFieldSelect = document.getElementById('imageFieldSelect');
    const hasImageFieldCheckbox = document.getElementById('hasImageField');
    const imageFieldContainer = document.getElementById('imageFieldContainer');
    const viewSelect = document.getElementById('viewSelect');
    const sortableColumns = document.getElementById('sortableColumns');
    const sortableFormFields = document.getElementById('sortableFormFields');
    const sameOrderCheckbox = document.getElementById('sameOrder');
    const tableOptions = document.getElementById('tableOptions');
    const listGridOptions = document.getElementById('listGridOptions');
    const carouselOptions = document.getElementById('carouselOptions');
    const prevStepBtn = document.getElementById('prevStep');
    const nextStepBtn = document.getElementById('nextStep');
    const confirmConfigBtn = document.getElementById('confirmConfig');
    let currentStep = 1;

    fieldList.innerHTML = '';
    imageFieldSelect.innerHTML = '<option value="">Select image field</option>';
    sortableColumns.innerHTML = '';
    sortableFormFields.innerHTML = '';

    const imageKeywords = ['image', 'photo', 'img', 'file', 'avatar', 'picture', 'profile_pic', 'cover', 'thumbnail', 'logo', 'icon', 'banner', 'filepath', 'filename', 'pic'];
    const nonEditableKeywords = ['created_at', 'updated_at', 'status'];
    const potentialImageField = columns.find(col => 
        !col.isPrimaryKey && col.type === 'VARCHAR' && imageKeywords.some(keyword => col.name.toLowerCase().includes(keyword)));
    const requiredKeywords = ['email', 'name', 'first_name', 'last_name'];

    columns.forEach(col => {
        const fieldId = `field_${col.name}`;
        const isRequired = !col.isPrimaryKey && requiredKeywords.some(keyword => col.name.toLowerCase().includes(keyword));
        const isNonEditable = col.isPrimaryKey || nonEditableKeywords.some(keyword => col.name.toLowerCase().includes(keyword));
        
        const fieldItem = document.createElement('div');
        fieldItem.className = 'bg-gray-50 p-4 rounded-lg border border-gray-200';
        fieldItem.innerHTML = `
            <div class="flex justify-between items-center mb-3">
                <h4 class="font-semibold text-gray-800">${col.name} <span class="text-sm text-gray-500">(${col.type}${col.isPrimaryKey ? ', PRIMARY KEY' : col.isUnique ? ', UNIQUE' : ''})</span></h4>
            </div>
            <div class="flex flex-wrap gap-4">
                ${!col.isPrimaryKey ? `
                <label class="inline-flex items-center">
                    <input type="checkbox" id="${fieldId}_form" class="form-checkbox h-4 w-4 text-blue-600 rounded" ${!isNonEditable && previousConfig[col.name]?.showInForm !== false ? 'checked' : ''}>
                    <span class="ml-2 text-sm text-gray-700">Show in Form</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="checkbox" id="${fieldId}_required" class="form-checkbox h-4 w-4 text-blue-600 rounded" ${!isNonEditable && (previousConfig[col.name]?.required || isRequired) ? 'checked' : ''}>
                    <span class="ml-2 text-sm text-gray-700">Required</span>
                </label>` : ''}
                <label class="inline-flex items-center">
                    <input type="checkbox" id="${fieldId}_view" class="form-checkbox h-4 w-4 text-blue-600 rounded" ${!isNonEditable && previousConfig[col.name]?.showInView !== false ? 'checked' : ''}>
                    <span class="ml-2 text-sm text-gray-700">Show in View</span>
                </label>
            </div>
        `;
        fieldList.appendChild(fieldItem);
        
        if (!col.isPrimaryKey && col.type === 'VARCHAR') {
            const option = document.createElement('option');
            option.value = col.name;
            option.textContent = `${col.name} (${col.type})`;
            imageFieldSelect.appendChild(option);
        }
    });

    if (previousHasImageField || potentialImageField) {
        hasImageFieldCheckbox.checked = previousHasImageField || !!potentialImageField;
        imageFieldSelect.value = previousImageField || (potentialImageField ? potentialImageField.name : '');
        imageFieldContainer.classList.remove('hidden');
        document.getElementById('imageFolder').value = previousViewOptions.imageFolder || '/uploads';
    } else {
        hasImageFieldCheckbox.checked = false;
        imageFieldContainer.classList.add('hidden');
    }

    viewSelect.value = previousView;
    document.getElementById('useDataTable').checked = previousViewOptions.useDataTable !== false;
    document.getElementById('tableSearch').checked = previousViewOptions.tableSearch !== false;
    document.getElementById('tablePagination').checked = previousViewOptions.tablePagination !== false;
    document.getElementById('tableRowHeight').value = previousViewOptions.tableRowHeight || 'md';
    document.getElementById('listGridSearch').checked = previousViewOptions.listGridSearch !== false;
    document.getElementById('listGridPagination').checked = previousViewOptions.listGridPagination !== false;
    document.getElementById('featuredStyle').checked = previousViewOptions.featuredStyle || false;
    document.getElementById('cardSpacing').value = previousViewOptions.cardSpacing || 'md';
    document.getElementById('carouselAutoplay').checked = previousViewOptions.carouselAutoplay || false;
    document.getElementById('carouselNav').checked = previousViewOptions.carouselNav !== false;
    document.getElementById('carouselDots').checked = previousViewOptions.carouselDots !== false;
    document.getElementById('carouselItems').value = previousViewOptions.carouselItems || 3;
    document.getElementById('carouselSpeed').value = previousViewOptions.carouselSpeed || 300;

    // Initialize color pickers
   
    ['primaryColor', 'secondaryColor', 'bodyBgColor', 'cardBgColor', 'textColor'].forEach(color => {
        colorPickers[color] = new Pickr({
            el: `#${color}Picker`,
            theme: 'classic',
            default: previousThemeOptions[color] || (color === 'primaryColor' ? '#000000' : color === 'secondaryColor' ? '#1F2937' : color === 'bodyBgColor' ? '#F9FAFB' : color === 'cardBgColor' ? '#FFFFFF' : '#374151'),
            components: {
                preview: true,
                opacity: true,
                hue: true,
                interaction: { hex: true, rgba: true, input: true, save: true }
            }
        });
    });

    document.getElementById('inputBorderRadius').value = previousThemeOptions.inputBorderRadius || 'md';
    document.getElementById('buttonStyle').value = previousThemeOptions.buttonStyle || 'rounded';
    document.getElementById('buttonSize').value = previousThemeOptions.buttonSize || 'md';
    document.getElementById('imageBorderStyle').value = previousThemeOptions.imageBorderStyle || 'md';
    document.getElementById('cardShadow').value = previousThemeOptions.cardShadow || 'md';
    document.getElementById('cardBorderStyle').value = previousThemeOptions.cardBorderStyle || 'md';
    document.getElementById('fontFamily').value = previousThemeOptions.fontFamily || 'Inter';
    document.getElementById('hoverEffect').value = previousThemeOptions.hoverEffect || 'scale';
    document.getElementById('padding').value = previousThemeOptions.padding || 'md';
    document.getElementById('margin').value = previousThemeOptions.margin || 'md';
    document.getElementById('gap').value = previousThemeOptions.gap || 'md';

    const viewColumns = columns.filter(col => previousConfig[col.name]?.showInView !== false && !col.isPrimaryKey && !nonEditableKeywords.includes(col.name.toLowerCase()));
    const formFields = columns.filter(col => previousConfig[col.name]?.showInForm !== false && !col.isPrimaryKey && !nonEditableKeywords.includes(col.name.toLowerCase()));
    const imageField = hasImageFieldCheckbox.checked ? imageFieldSelect.value : null;

    const sortedColumns = previousColumnOrder.length > 0 
        ? previousColumnOrder.filter(colName => viewColumns.some(col => col.name === colName))
        : (imageField ? [imageField, ...viewColumns.filter(col => col.name !== imageField).map(col => col.name)] : viewColumns.map(col => col.name));

    const sortedFormFields = previousFormFieldOrder.length > 0
        ? previousFormFieldOrder.filter(colName => formFields.some(col => col.name === colName))
        : formFields.map(col => col.name);

    sortedColumns.forEach(colName => {
        const col = viewColumns.find(c => c.name === colName);
        if (col) {
            const columnItem = document.createElement('div');
            columnItem.className = 'sortable-column border border-gray-200 rounded-lg';
            columnItem.dataset.column = col.name;
            columnItem.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-700">${col.name}</span>
                    <i class="fas fa-grip-vertical text-gray-400"></i>
                </div>
            `;
            sortableColumns.appendChild(columnItem);
        }
    });

    sortedFormFields.forEach(colName => {
        const col = formFields.find(c => c.name === colName);
        if (col) {
            const fieldItem = document.createElement('div');
            fieldItem.className = 'sortable-form-field border border-gray-200 rounded-lg';
            fieldItem.dataset.column = col.name;
            fieldItem.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-700">${col.name}</span>
                    <i class="fas fa-grip-vertical text-gray-400"></i>
                </div>
            `;
            sortableFormFields.appendChild(fieldItem);
        }
    });

    new Sortable(sortableColumns, {
        animation: 150,
        ghostClass: 'bg-blue-100',
        handle: '.sortable-column'
    });

    new Sortable(sortableFormFields, {
        animation: 150,
        ghostClass: 'bg-blue-100',
        handle: '.sortable-form-field'
    });

    sameOrderCheckbox.onchange = (e) => {
        if (e.target.checked) {
            sortableFormFields.innerHTML = '';
            sortedColumns.forEach(colName => {
                const col = formFields.find(c => c.name === colName);
                if (col) {
                    const fieldItem = document.createElement('div');
                    fieldItem.className = 'sortable-form-field border border-gray-200 rounded-lg';
                    fieldItem.dataset.column = col.name;
                    fieldItem.innerHTML = `
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">${col.name}</span>
                            <i class="fas fa-grip-vertical text-gray-400"></i>
                        </div>
                    `;
                    sortableFormFields.appendChild(fieldItem);
                }
            });
            new Sortable(sortableFormFields, { animation: 150, ghostClass: 'bg-blue-100', handle: '.sortable-form-field' });
        }
    };

    function updateStepIndicator() {
        document.querySelectorAll('.step-circle').forEach((circle, index) => {
            circle.classList.toggle('active', index + 1 === currentStep);
        });
        document.querySelectorAll('.step-line').forEach((line, index) => {
            line.classList.toggle('active', index < currentStep - 1);
        });
        prevStepBtn.classList.toggle('hidden', currentStep === 1);
        nextStepBtn.classList.toggle('hidden', currentStep === 5);
        confirmConfigBtn.classList.toggle('hidden', currentStep !== 5);
    }

    function showStep(step) {
        document.querySelectorAll('.wizard-step').forEach(s => s.classList.remove('active'));
        document.getElementById(`step${step}`).classList.add('active');
        currentStep = step;
        updateStepIndicator();

        if (currentStep === 4) {
            sortableColumns.innerHTML = '';
            sortableFormFields.innerHTML = '';
            const viewColumns = columns.filter(col => document.getElementById(`field_${col.name}_view`)?.checked && !nonEditableKeywords.includes(col.name.toLowerCase()));
            const formFields = columns.filter(col => document.getElementById(`field_${col.name}_form`)?.checked && !col.isPrimaryKey && !nonEditableKeywords.includes(col.name.toLowerCase()));
            const imageField = hasImageFieldCheckbox.checked ? imageFieldSelect.value : null;

            const sortedColumns = previousColumnOrder.length > 0 
                ? previousColumnOrder.filter(colName => viewColumns.some(col => col.name === colName))
                : (imageField ? [imageField, ...viewColumns.filter(col => col.name !== imageField).map(col => col.name)] : viewColumns.map(col => col.name));

            const sortedFormFields = sameOrderCheckbox.checked ? sortedColumns : (previousFormFieldOrder.length > 0 
                ? previousFormFieldOrder.filter(colName => formFields.some(col => col.name === colName))
                : formFields.map(col => col.name));

            sortedColumns.forEach(colName => {
                const col = viewColumns.find(c => c.name === colName);
                if (col) {
                    const columnItem = document.createElement('div');
                    columnItem.className = 'sortable-column border border-gray-200 rounded-lg';
                    columnItem.dataset.column = col.name;
                    columnItem.innerHTML = `
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">${col.name}</span>
                            <i class="fas fa-grip-vertical text-gray-400"></i>
                        </div>
                    `;
                    sortableColumns.appendChild(columnItem);
                }
            });

            sortedFormFields.forEach(colName => {
                const col = formFields.find(c => c.name === colName);
                if (col) {
                    const fieldItem = document.createElement('div');
                    fieldItem.className = 'sortable-form-field border border-gray-200 rounded-lg';
                    fieldItem.dataset.column = col.name;
                    fieldItem.innerHTML = `
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-700">${col.name}</span>
                            <i class="fas fa-grip-vertical text-gray-400"></i>
                        </div>
                    `;
                    sortableFormFields.appendChild(fieldItem);
                }
            });

            new Sortable(sortableColumns, { animation: 150, ghostClass: 'bg-blue-100', handle: '.sortable-column' });
            new Sortable(sortableFormFields, { animation: 150, ghostClass: 'bg-blue-100', handle: '.sortable-form-field' });
        }
    }

    prevStepBtn.onclick = () => {
        if (currentStep > 1) showStep(currentStep - 1);
    };

    nextStepBtn.onclick = () => {
        if (currentStep === 2 && hasImageFieldCheckbox.checked && !imageFieldSelect.value) {
            document.getElementById('imageFieldSelect').classList.add('border-red-500');
            document.getElementById('error_imageField').classList.remove('hidden');
            return;
        }
        if (currentStep === 2 && hasImageFieldCheckbox.checked) {
            const imageFolder = document.getElementById('imageFolder').value;
            if (!/^[a-zA-Z0-9\/]+$/.test(imageFolder) || !imageFolder.startsWith('/')) {
                document.getElementById('imageFolder').classList.add('border-red-500');
                document.getElementById('error_imageFolder').classList.remove('hidden');
                return;
            }
        }
        if (currentStep === 3 && viewSelect.value === 'carousel') {
            const items = parseInt(document.getElementById('carouselItems').value);
            if (isNaN(items) || items < 1 || items > 10) {
                document.getElementById('carouselItems').classList.add('border-red-500');
                document.getElementById('error_carouselItems').classList.remove('hidden');
                return;
            }
            const speed = parseInt(document.getElementById('carouselSpeed').value);
            if (isNaN(speed) || speed < 100 || speed > 2000) {
                document.getElementById('carouselSpeed').classList.add('border-red-500');
                document.getElementById('error_carouselSpeed').classList.remove('hidden').textContent = 'Transition speed must be between 100 and 2000 ms';
                return;
            }
        }
        if (currentStep < 5) showStep(currentStep + 1);
    };

    document.getElementById('cancelConfig').onclick = () => {
        modal.classList.remove('opacity-100', 'visible');
        modal.classList.add('opacity-0', 'invisible');
        Object.values(colorPickers).forEach(picker => picker.destroy());
    };

    [tableOptions, listGridOptions, carouselOptions].forEach(opt => opt.classList.add('hidden'));
    if (viewSelect.value === 'table') tableOptions.classList.remove('hidden');
    else if (['list', 'grid'].includes(viewSelect.value)) listGridOptions.classList.remove('hidden');
    else if (viewSelect.value === 'carousel') carouselOptions.classList.remove('hidden');

    viewSelect.onchange = (e) => {
        [tableOptions, listGridOptions, carouselOptions].forEach(opt => opt.classList.add('hidden'));
        if (e.target.value === 'table') tableOptions.classList.remove('hidden');
        else if (['list', 'grid'].includes(e.target.value)) listGridOptions.classList.remove('hidden');
        else if (e.target.value === 'carousel') carouselOptions.classList.remove('hidden');
    };

    modal.classList.remove('opacity-0', 'invisible');
    modal.classList.add('opacity-100', 'visible');
    showStep(1);
}

async function generateFiles() {
    const sql = document.getElementById('sqlInput').value.trim();
    if (!sql) {
        alert('Please enter a valid SQL table structure');
        return;
    }

    const generateBtn = document.getElementById('generateBtn');
    const btnText = document.getElementById('btnText');
    const loadingSpinner = document.getElementById('loadingSpinner');
    
    generateBtn.disabled = true;
    btnText.textContent = 'Parsing...';
    loadingSpinner.classList.remove('hidden');

    try {
        const response = await $.ajax({
            url: 'generator.php',
            type: 'POST',
            data: { action: 'parse_sql', sql: sql },
            dataType: 'json'
        });

        if (!response.success) {
            throw new Error(response.message || 'Invalid SQL syntax');
        }

        const { table, columns } = response.data;
        document.getElementById('htmlWidgetTitle').textContent = `Widget: ${table}`;
        document.getElementById('phpWidgetTitle').textContent = `Widget: ${table}-api`;

        let previousConfig = {};
        let previousImageField = null;
        let previousHasImageField = false;
        let previousView = 'table';
        let previousColumnOrder = [];
        let previousFormFieldOrder = [];
        let previousViewOptions = {};
        let previousThemeOptions = {};

        showConfigModal(columns, previousConfig, previousImageField, previousHasImageField, previousView, previousColumnOrder, previousFormFieldOrder, previousViewOptions, previousThemeOptions);
        
        document.getElementById('cancelConfig').onclick = () => {
            document.getElementById('configModal').classList.remove('opacity-100', 'visible');
            document.getElementById('configModal').classList.add('opacity-0', 'invisible');
            generateBtn.disabled = false;
            btnText.textContent = 'Generate Files';
            loadingSpinner.classList.add('hidden');
        };
        
        document.getElementById('confirmConfig').onclick = async () => {
            const hasImageField = document.getElementById('hasImageField').checked;
            const imageField = hasImageField ? document.getElementById('imageFieldSelect').value : null;
            const imageFolder = document.getElementById('imageFolder').value;
            const view = document.getElementById('viewSelect').value;
            
            if (hasImageField && !imageField) {
                document.getElementById('imageFieldSelect').classList.add('border-red-500');
                document.getElementById('error_imageField').classList.remove('hidden');
                return;
            }
            if (hasImageField && !/^[a-zA-Z0-9\/]+$/.test(imageFolder) || !imageFolder.startsWith('/')) {
                document.getElementById('imageFolder').classList.add('border-red-500');
                document.getElementById('error_imageFolder').classList.remove('hidden');
                return;
            }
            if (view === 'carousel') {
                const items = parseInt(document.getElementById('carouselItems').value);
                if (isNaN(items) || items < 1 || items > 10) {
                    document.getElementById('carouselItems').classList.add('border-red-500');
                    document.getElementById('error_carouselItems').classList.remove('hidden');
                    return;
                }
                const speed = parseInt(document.getElementById('carouselSpeed').value);
                if (isNaN(speed) || speed < 100 || speed > 2000) {
                    document.getElementById('carouselSpeed').classList.add('border-red-500');
                    document.getElementById('error_carouselSpeed').classList.remove('hidden').textContent = 'Transition speed must be between 100 and 2000 ms';
                    return;
                }
            }

            const fieldConfig = {};
            columns.forEach(col => {
                const fieldId = `field_${col.name}`;
                fieldConfig[col.name] = {
                    showInForm: col.isPrimaryKey ? false : document.getElementById(`${fieldId}_form`)?.checked ?? false,
                    showInView: document.getElementById(`${fieldId}_view`)?.checked ?? false,
                    required: col.isPrimaryKey ? false : document.getElementById(`${fieldId}_required`)?.checked ?? false
                };
            });

            const sortableColumns = document.getElementById('sortableColumns');
            const columnOrder = Array.from(sortableColumns.children).map(item => item.dataset.column);
            const sortableFormFields = document.getElementById('sortableFormFields');
            const formFieldOrder = document.getElementById('sameOrder').checked 
                ? columnOrder 
                : Array.from(sortableFormFields.children).map(item => item.dataset.column);
            
            const viewOptions = {
                imageFolder: hasImageField ? imageFolder : '/uploads',
                useDataTable: document.getElementById('useDataTable').checked,
                tableSearch: document.getElementById('tableSearch').checked,
                tablePagination: document.getElementById('tablePagination').checked,
                tableRowHeight: document.getElementById('tableRowHeight').value,
                listGridSearch: document.getElementById('listGridSearch').checked,
                listGridPagination: document.getElementById('listGridPagination').checked,
                featuredStyle: document.getElementById('featuredStyle').checked,
                cardSpacing: document.getElementById('cardSpacing').value,
                carouselAutoplay: document.getElementById('carouselAutoplay').checked,
                carouselNav: document.getElementById('carouselNav').checked,
                carouselDots: document.getElementById('carouselDots').checked,
                carouselItems: parseInt(document.getElementById('carouselItems').value),
                carouselSpeed: parseInt(document.getElementById('carouselSpeed').value)
            };

            const themeOptions = {
                primaryColor: colorPickers.primaryColor.getColor().toHEXA().toString(),
                secondaryColor: colorPickers.secondaryColor.getColor().toHEXA().toString(),
                bodyBgColor: colorPickers.bodyBgColor.getColor().toHEXA().toString(),
                cardBgColor: colorPickers.cardBgColor.getColor().toHEXA().toString(),
                textColor: colorPickers.textColor.getColor().toHEXA().toString(),
                inputBorderRadius: document.getElementById('inputBorderRadius').value,
                buttonStyle: document.getElementById('buttonStyle').value,
                buttonSize: document.getElementById('buttonSize').value,
                imageBorderStyle: document.getElementById('imageBorderStyle').value,
                cardShadow: document.getElementById('cardShadow').value,
                cardBorderStyle: document.getElementById('cardBorderStyle').value,
                fontFamily: document.getElementById('fontFamily').value,
                hoverEffect: document.getElementById('hoverEffect').value,
                padding: document.getElementById('padding').value,
                margin: document.getElementById('margin').value,
                gap: document.getElementById('gap').value
            };

            generateBtn.disabled = true;
            btnText.textContent = 'Generating...';
            loadingSpinner.classList.remove('hidden');
            
            document.getElementById('configModal').classList.remove('opacity-100', 'visible');
            document.getElementById('configModal').classList.add('opacity-0', 'invisible');
            const generateResponse = await $.ajax({
                url: 'generator.php',
                type: 'POST',
                data: {
                    action: 'generate',
                    table: table,
                    columns: JSON.stringify(columns),
                    fieldConfig: JSON.stringify(fieldConfig),
                    imageField: imageField,
                    view: view,
                    columnOrder: JSON.stringify(columnOrder),
                    formFieldOrder: JSON.stringify(formFieldOrder),
                    viewOptions: JSON.stringify(viewOptions),
                    themeOptions: JSON.stringify(themeOptions),
                    sql: sql
                },
                dataType: 'json'
            });

            if (!generateResponse.success) {
                throw new Error(generateResponse.message || 'Error generating files');
            }

            document.getElementById('resultsSection').classList.remove('hidden');
            document.getElementById('htmlOutput').textContent = generateResponse.data.html;
            document.getElementById('phpOutput').textContent = generateResponse.data.php;
            const token = generateResponse.data.token;
            if (token) {
                const projectUrl = `/projects/${token}`;
                $('#viewProjectLink').attr('href', projectUrl);
                
                // Copy to clipboard with celebration
                $('#copyProjectLink').off('click').on('click', function() {
                    navigator.clipboard.writeText(window.location.origin + projectUrl)
                        .then(() => {
                            $(this).text('🎉 Copied!').prop('disabled', true);
                            setTimeout(() => {
                                $(this).text('📋 Copy Link').prop('disabled', false);
                            }, 2000);
                        });
                });
            
                $('#projectLinkSection').removeClass('hidden');
            }

            generateBtn.disabled = false;
            btnText.textContent = 'Generate Files';
            loadingSpinner.classList.add('hidden');
        };
        
        document.getElementById('hasImageField').onchange = (e) => {
            document.getElementById('imageFieldContainer').classList.toggle('hidden', !e.target.checked);
        };
        
        document.getElementById('imageFieldSelect').onchange = () => {
            document.getElementById('error_imageField').classList.add('hidden');
        };
        
        document.getElementById('imageFolder').oninput = () => {
            document.getElementById('error_imageFolder').classList.add('hidden');
            document.getElementById('imageFolder').classList.remove('border-red-500');
        };
        
        document.getElementById('carouselItems').oninput = () => {
            const input = document.getElementById('carouselItems');
            const value = parseInt(input.value);
            if (value < 1) input.value = 1;
            if (value > 10) input.value = 10;
            document.getElementById('error_carouselItems').classList.add('hidden');
            document.getElementById('carouselItems').classList.remove('border-red-500');
        };

        document.getElementById('carouselSpeed').oninput = () => {
            const input = document.getElementById('carouselSpeed');
            const value = parseInt(input.value);
            if (value < 100) input.value = 100;
            if (value > 2000) input.value = 2000;
            document.getElementById('error_carouselSpeed').classList.add('hidden');
            document.getElementById('carouselSpeed').classList.remove('border-red-500');
        };
    } catch (error) {
        console.error('Error generating files:', error);
        alert('Error: ' + error.message);
        generateBtn.disabled = false;
        btnText.textContent = 'Generate Files';
        loadingSpinner.classList.add('hidden');
    }
}

document.getElementById('generateBtn').onclick = generateFiles;


$(document).ready(function() {
    if (window.projectData) {
        $('#sqlInput').val(window.projectData.sql_query);
        $('#htmlOutput').text(window.projectData.generated_html || '');
        $('#phpOutput').text(window.projectData.generated_php || '');
        $('#resultsSection').removeClass('hidden');
    }
});


    </script>
</body>
</html>