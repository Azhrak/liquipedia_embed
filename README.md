# Liquipedia Embed Script

Reads a StarCraft II Liquipedia page (https://liquipedia.net/starcraft2/*) and parses the groups and brackets to an array. The script also contains example print methods to produce HTML groups and brackets from the parsed data.

This script can be used to print Liquipedia results on a different web page in an iframe for example. The fetched data is cached to reduce the load on the source web server.

## Example use

```
<iframe src="https://example.com/lp_embed.php?mode=bracket&amp;url=https://liquipedia.net/starcraft2/HomeStory_Cup/12"></iframe>
```
Print options are given in GET parameters. See lp_embed.php for options.

## TODO

* Parse double elimination brackets.
* Parse and print player list.
* Parse and print tournament results.
* Option to print all groups and brackets on same page.
