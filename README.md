
# MyTube - Simple DIY web interface for videos

MyTube is a simple web interface to list and allow playing a collection of videos.
Ideal to provide a safe, local space for kids to explore.

## Features

- Signs requests to protect against accessing arbitrary parts of the filesystem
- Extracts thumbnails from videos
- Lazy loads thumbnails
- Renames files to remove problematic characters

## Requirements

- A web server capable of running PHP scripts, for example, Apache
- `ffmpeg` to extract frames from videos

## Deployment

1. Choose a location for the code, and copy the files in this repository there. This can be the same as your video directory.
2. Configure the webserver to expose this location. This will be where the web interface can be accessed.
3. Choose a location for the videos, and set `$BASEDIR` in `index.php` to this location.
4. Configure the webserver to expose this location, too (if different from the code location), and add the base URL to `$BASEURL`.
5. Add a random string to `$SIGN_SALT`.
6. Ensure that the script will have permission to write to the video directory. In Linux Apache usually runs as the user group `www-data`. Then the following commands can ensure this user can write the video directory: `chown <my user>:www-data <video dir>`, `chmod g+rwx <video dir>`.
7. Ensure ffmpeg is installed. On Debian, use `sudo apt install ffmpeg`.

## Child safety

To allow a child to watch the videos on their device, the following can offer guidance:
- Set up MyTube on a server that has a static IP or a hostname that resolves to it.
- The child's device can access the videos using a web browser (that will need to be able to play the videos directly).
- On Android, use Family Link and Chrome to restrict Chrome to the URL of the server only.

## Notes

- MyTube stores thumbnails as jpg files in the video directory. It looks for the thumbnail under the name `<videofile>.jpg`, for example, `my_video.mp4.jpg`. You can create the thumbnails yourself to use custom thumbnails.
- MyTube links to the video files directly and relies on the browser to play the videos.
- Currently only files ending in `.mp4` and directories ending in `.dir` are listed. This is to avoid listing code files, but can be easily changed.
- MyTube uses a simple templating system to render the webpages; see `Solder.php` for more.
