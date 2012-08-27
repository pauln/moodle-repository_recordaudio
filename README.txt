Record Audio repository plugin for Moodle 2.3

INTRODUCTION:
The Record Audio repository plugin allows users to simply record a snippet of audio (in MP3 format) instead of uploading (or choosing) a file, anywhere that Moodle 2 provides access to the file picker.  No streaming server is required to use this plugin; recording is done entirely on the user's computer, with the file uploaded via HTTP.

REQUIREMENTS:
Moodle 2.3.1+ (Build 20120816) or newer
Flash Player 10.1 or higher
JavaScript enabled in your browser

INSTALLATION:
- Place the "recordaudio" directory within your_moodle_install/repository/
- Visit the admin notifications page and complete the installation
- Under "Site Administration -> Plugins -> Repositories -> Manage Repositories":
  - Find "Record Audio" and choose "Enabled and visible" in the dropdown beside it
  - Optionally specify a custom name for the repository (the default is "Record Audio")
  - Click "Save"
  - Optionally use the arrows in the "Order" column to change its position in the list of active repositories
- Done!