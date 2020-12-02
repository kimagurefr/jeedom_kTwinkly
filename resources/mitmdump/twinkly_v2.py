import random
import sys
import typing
import json
import os
from mitmproxy import io, http, ctx

class Twinkly:

    def __init__(self) -> None:
        self.movie = None
        self.leds = 0
        self.frames = 0
        self.delay = 0
        self.num = 1

    def load(self, loader):
        loader.add_option(
            name = "filename",
            typespec = typing.Optional[str],
            default = "/tmp/tmovie",
            help = "Emplacement du fichier de dump"
        )
        loader.add_option(
            name = "ipaddress",
            typespec = str,
            default = "",
            help = "Adresse IP de la guirlande"
        )

    def response(self, flow: http.HTTPFlow) -> None:
        if flow.request.pretty_url == f"http://{ctx.options.ipaddress}/xled/v1/movies/new":
            ctx.log.info(f"First (movie info)")
            #config = flow.request.content
            config = flow.request.text
            descriptor = str(ctx.options.filename) + "-" + str(self.num) + ".json"
            fd = open(descriptor, "w")
            fd.write(str(config))
            fd.close()


        if flow.request.pretty_url == f"http://{ctx.options.ipaddress}/xled/v1/movies/full":
            ctx.log.info(f"Second step (movie)")
            movie = flow.request.content
            binfile = str(ctx.options.filename) + "-" + str(self.num) + ".bin"
            fb: typing.IO[bytes] = open(binfile, "wb")
            fb.write(movie)
            fb.close()
            self.num = self.num + 1

addons = [Twinkly()]
