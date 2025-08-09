# CRUD Generator

A web-based tool to generate fully customizable CRUD (Create, Read, Update, Delete) files using jQuery AJAX and Brilliant Directories queries. Enter your SQL table structure and configure fields, views, and themes to instantly generate HTML and PHP code for your project.

## Features

- SQL table structure parsing
- Field and form configuration
- Image field support
- Multiple view types: Table, List, Grid, Carousel
- Theme customization (colors, fonts, spacing, etc.)
- Drag-and-drop column and form field ordering
- Generates ready-to-use HTML and PHP files
- Project saving and sharing via unique token links

## Getting Started

1. Clone the repository:
    ```sh
    git clone https://github.com/mforfiaz/crud-generator.git
    cd crud-generator
    ```

2. Set up your database connection in `index.php`, `projects.php`, and `generator.php`.

3. Open `index.php` in your browser to start generating CRUD files.

## File Structure

- `index.php` — Main generator interface
- `projects.php` — Project viewer via tokenized links
- `generator.php` — Backend logic for parsing SQL and generating code
- `.htaccess` — URL rewriting for project tokens

## Usage

1. Paste your SQL table structure in the input box.
2. Configure fields, image options, view type, and theme.
3. Generate files and copy/share your project link.

## License

MIT

---

Developed by Fiaz Zafar. Visit [mforfiaz.com](https://mforfiaz.com/) for more projects.
