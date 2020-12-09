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
        if flow.request.pretty_url == f"http://{ctx.options.ipaddress}/xled/v1/led/movie/full":
            ctx.log.info(f"First step (movie)")
            self.movie = flow.request.content
            binfile = str(ctx.options.filename) + "-" + str(self.num) + ".bin"
            self.fb: typing.IO[bytes] = open(binfile, "wb")
            self.fb.write(self.movie)
            self.fb.close()

        if flow.request.pretty_url == f"http://{ctx.options.ipaddress}/xled/v1/led/movie/config":
            ctx.log.info(f"Second step (config)")
            config = json.loads(flow.request.content)
            jsondata = '{"leds_number": %s, "frames_number": %s, "frame_delay": %s}' % (config['leds_number'],config['frames_number'],config['frame_delay'])
            jsonfile = str(ctx.options.filename) + "-" + str(self.num) + ".json"
            self.fs = open(jsonfile, "w")
            self.fs.write(jsondata)
            self.fs.close()
            self.num = self.num + 1
            
addons = [Twinkly()]
