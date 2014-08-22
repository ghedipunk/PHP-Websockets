PHP WebSockets
==============

A WebSockets server written in PHP.
-----------------------------------

This project provides the functionality of an RFC-6455 (or Version 13) WebSockets server.  It can be used as a stand-alone server, or as the back-end of a normal HTTP server that is WebSockets aware.

In order to use PHP WebSockets, you must have the ability to arbitrarilly execute scripts, which almost always means having shell access to your server, at a minimum.  It is strongly encouraged that you have the ability to configure your machine's HTTP server.  It is strongly discouraged to allow arbitrary execution of scripts from a web interface, as this is a major security hole.

To use:

Do not place the files in your web server's document root -- they are not intended to be ran through a web browser or otherwise directly accessible to the world.  They are intended to be ran through PHP's Command Line Interface (CLI).

The main class, `WebSocketServer`, is intended to be inherrited by your class, and the methods `connected`, `closed`, and `process` should be overridden.  In fact, they are abstract, so they _must_ be overridden.

Future plans include allowing child processes forked from the controlling daemon to support broadcasts and to relay data from one socket in a child process to another socket in a separate child proccess.

Browser Support
---------------

Broswer Name        Earliest Version

Google Chrome       16

Mozilla Firefox     11

Internet Explorer   10

Safari              6

Opera               12.10

Android Browser     4.4

Note: Current browser support is available at http://en.wikipedia.org/wiki/WebSocket#Browser_support under the RFC-6455 row.
