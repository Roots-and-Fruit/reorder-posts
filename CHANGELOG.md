# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/2.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- Reorder screens use the same layout as Settings. Folder tabs show only when Reorder by Term or Reorder Terms adds one for that post type.

### Fixed

- Dragging a title on a tablet selects the text instead of moving the row. Drag from the grip. Add-on reorder lists get the same grip.

## [2.6.2] - 2026-09-28

### Changed

- Settings are grouped into tabs. Existing labels and saved values are unchanged. Add-on sections get a tab when they are active.

## [2.6.1] - 2026-05-02

### Changed

- Fix: Resolving PHP error notices for undefined indexes when saving admin settings.

## [2.6.0] - 2026-03-23

### Changed

- Fix: Default post types that are enabled are only post and page. The rest should be opt-in.
- Fix: All post types were having menus registered, even when they weren't explicitly enabled.
- New: Reorder Posts shortcut shows up next to each post type in admin settings (if enabled).
- New: Post types with no top-level menu now are visible at the root level and can be accessed via the admin shortcuts.

## [2.5.3] - 2022-05-27

### Changed

- Cache busting for people who are receiving script errors around Nested Sortable.

## [2.5.1] - 2022-02-14

### Changed

- Fixing sortable script with newer versions of jQuery sortable.

## [2.5.0] - 2020-11-18

### Changed

- Fixing sortable script with newer versions of jQuery.

## [2.4.1] - 2019-02-16

### Changed

- Added query to reorder posts for an example
- Option to turn off query output

## [2.4.0] - 2016-08-14

### Changed

- Major CSS overhaul inspired by the Nested Pages plugin
- The sorting is now mobile friendly

## [2.3.0] - 2016-08-12

### Changed

- Added screen options to set the number of posts displayed

## [2.2.2] - 2015-12-04

### Changed

- Fixed loading animation that displays out of nowhere

## [2.2.1] - 2015-11-09

### Changed

- Fixed pagination issue

## [2.2.0] - 2015-11-01

### Changed

- Loading animation now shows inline

## [2.1.5] - 2015-10-02

### Changed

- Fixing paging offset error in the backend.

## [2.1.4] - 2015-04-24

### Changed

- Updated 2015-08-20 for WordPress 4.3
- Added cache-busting when re-ordering
- Added German translation
- Ensuring WordPress 4.2 compatibility

## [2.1.2] - 2015-01-28

### Changed

- Removed developer notice from Reorder pages

## [2.1.1] - 2015-01-21

### Changed

- Fixed pagination issue
- Improved Reorder save query performance significantly

## [2.1.0] - 2015-01-19

### Changed

- Added add-on support
- Make sure reordering can only be done by those with edit_pages privileges
- Added pagination for performance reasons
- Optimized queries for performance reasons
- Added warning message for those with a lot of posts

## [2.0.2] - 2014-12-26

### Changed

- Bug fix:  Saving admin panel settings resulted in a variety of PHP offset error messages.
- Bug fix:  Querying multiple post types resulted in PHP illegal offset error messages.

## [2.0.1] - 2014-12-23

### Changed

- Altered contributor documentation.
- Adding filters for determining where the Reorder sub-menu will show up.
- Sub-menu headings now reflect the post type that is being re-ordered.
- Fixed bug in display when there are no post types to re-order.
- Changed class names to be more unique.

## [2.0.0] - 2014-12-12

### Changed

- Added settings panel for enabling/disabling the Reorder plugin for post types.
- Added advanced settings for overriding the menu order of post types.
- Added internationalization capabilities.
- Slightly adjusted the styles of the Reordering interface.

## [1.0.6] - 2013-07-19

### Changed

- Updated 2014-12-11 - Ensuring WordPress 4.1 compatibility
- Added new filter for editing the post-types supported
- Thanks to mathielo for the suggestion and code contribution.

## [1.0.5] - 2012-08-09

### Changed

- Added expand/collapse section for nested post types
- Added better page detection for scripts and styles

## [1.0.4] - 2012-07-11

### Changed

- Added support for hierarchical post types

## [1.0.3] - 2012-05-09

### Changed

- Updated screenshot
- Corrected function prefix
- Additional: changed readme.txt (didn't bump version number)

## [1.0.2] - 2012-05-09

### Changed

- Added ability to post type of posts to be reordered
- Fixed bug in initial order

## [1.0.1]

### Changed

- Added ability to change menu name via class argument
- Removed support for non-hierarchical post-types

## [1.0]

### Changed

- Initial plugin release

[Unreleased]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.6.2...HEAD
[2.6.2]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.6.1...2.6.2
[2.6.1]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.6.0...2.6.1
[2.6.0]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.5.3...2.6.0
[2.5.3]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.5.1...2.5.3
[2.5.1]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.5.0...2.5.1
[2.5.0]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.4.1...2.5.0
[2.4.1]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.4.0...2.4.1
[2.4.0]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.3.0...2.4.0
[2.3.0]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.2.2...2.3.0
[2.2.2]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.2.1...2.2.2
[2.2.1]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.1.5...2.2.0
[2.1.5]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.1.4...2.1.5
[2.1.4]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.1.2...2.1.4
[2.1.2]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.1.1...2.1.2
[2.1.1]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.0.2...2.1.0
[2.0.2]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.0.1...2.0.2
[2.0.1]: https://github.com/Roots-and-Fruit/reorder-posts/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/Roots-and-Fruit/reorder-posts/compare/1.0.6...2.0.0
[1.0.6]: https://github.com/Roots-and-Fruit/reorder-posts/compare/1.0.5...1.0.6
[1.0.5]: https://github.com/Roots-and-Fruit/reorder-posts/compare/1.0.4...1.0.5
[1.0.4]: https://github.com/Roots-and-Fruit/reorder-posts/compare/1.0.3...1.0.4
[1.0.3]: https://github.com/Roots-and-Fruit/reorder-posts/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/Roots-and-Fruit/reorder-posts/releases/tag/1.0.2
