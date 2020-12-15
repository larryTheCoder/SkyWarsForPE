<!-- 
  The artist for this profile picture is gatodelfuturo
  Twitter: @GatodelFuturo
  Tumblr: https://gatodelfuturo.tumblr.com
 -->
<h1>SkyWarsForPE<img src="https://cdn.discordapp.com/attachments/512987829970665482/785515846092849198/PuppyBox-new-h-trans.png" height="200" width="200" align="right"></img></h1>

[![Poggit-CI](https://poggit.pmmp.io/ci.shield/larryTheCoder/SkyWarsForPE/SkyWarsForPE)](https://poggit.pmmp.io/ci/larryTheCoder/SkyWarsForPE/SkyWarsForPE) [![Donate](https://img.shields.io/badge/donate-PayPal-yellow.svg?style=flat-square)](http://www.paypal.me/Permeable)

    Powered by Open Sourced code, Our code comrade.
    Its time to burn those old fancy paid plugins and beat them in face!

## Introduction
SkyWarsForPE is a production-standard plugin built for all Minecraft PE Community, the code was acquired by Alair069 for
SkyWars project/development. However, due to some inactivity in this project in the self-isolated environment, it was brought
back to be open-sourced again, and this plugin will continue to compete with other SkyWars plugin. I ensure you, this plugin 
is more powerful than ever before. It's all for free! The plugin is currently in a state of testing-stage, 
some operations in this core subroutine might be faulty but has been completed.

The core itself uses a very basic principle in which it is async-capable, performance-capable and configurable.

This is the example of my work if you want to commission me, my prices are vary from $50-$200 in which depends on what
type of work do you want me to do.

*Well it would be nice if you star this project if it helps your server :)*

## How to download?
I personally recommend you to download .phar builds in our poggit builds, it is easier to keep track which version
you are using that encountered a bug. Download link is [available here](https://poggit.pmmp.io/ci/larryTheCoder/SkyWarsForPE/SkyWarsForPE). 

### Running from source
Running the code from source is not recommended. However, you can run this plugin from source with [DeVirion](https://poggit.pmmp.io/ci/poggit/devirion/DEVirion/dev:33).
After installing DeVirion in `/plugins` path. Download [libasynql](https://poggit.pmmp.io/ci/poggit/libasynql/libasynql/dev:137) binaries, make sure it is in `master` branch and not `4.0`.
Copy libasynql in `/virions` folder, and the plugin will start normally.

## Implemented features:
- Asynchronous world loading/unloading. (Technically just fast)
- GUI handled arena setup and settings menu.
- More commands, A lot of working commands.
- Configurable arenas and easy-to-work with documentations.
- Faster load startup and less load on the server.
- Arenas worlds compressed in Bzip2 format to ensure more disk storage/volumes.
- NPC top winners, these NPC has fully written all over again to not crash.
- Added kits compatibility with [EasyKits](https://github.com/AndreasHGK/EasyKits) plugin.
- Mysql/Sqlite operations is asynchronous by libasynql
- All the player stats will be stored centrally in sql/mysql database.
- Scoreboard has been implemented, it can be configured in [scoreboard.yml](https://github.com/larryTheCoder/SkyWarsForPE/blob/master/resources/scoreboard.yml).
- Portable GameAPI, you can take a look at the `larryTheCoder/arena/api` path for more info.
- Team mode implementation is now available, you can now play in teams.
- Extreme precision on death/kills checks with CombatLogger implementation.
- Queue methods can now be changed with custom plugin.
- Aesthetics sound effects in-game. 

## Configuration
Our [wikipage](https://github.com/larryTheCoder/SkyWarsForPE/wiki) will be updated frequently, so check out the page for more useful
tips on how to configure the arena and so on. 

### Using EasyKits for SkyWarsForPE.
This is fairly easy, your kit will automatically be listed in the kit selection item. However,
the kit will **only be listed** if the permission were set to `sw.internal.*`. Other than that, you're done configuring
the kits for this plugin.

You can use this permission to only allows specific players to access this kit (i.e `sw.internal.*`). For an example,
you can set Player A to only access this kit with permission `sw.internal.kit-a`, and Player B, `sw.internal.kit-b`.
Player A cannot access Player's B kit and vice versa. 

## Commands

| Default command | Parameter | Description | Default Permission |
| :-----: | :-------: | :---------: | :-------: |
| /sw |`<args>` | Main SkyWars command | `All` |
| /sw lobby | | back to lobby | `All` |
| /sw help | | Get command map | `All` |
| /sw cage | | Set your own cage | `All` |
| /sw stats | | Show the player stats | `ALL`|
| /sw npc | | Creates a top winner NPC | `OP` |
| /sw create | `<Arena Name>` | create an arena for SkyWars | `OP` |
| /sw start | `<Arena Name>` | Start the game | `OP` |
| /sw stop | `<Arena Name>` | Stop an arena | `OP` |
| /sw join | `<Arena Name>` | join an arena | `All` |
| /sw settings | | Open settings GUI | `OP` |
| /sw setlobby | | Set the main lobby for the plugin | `OP` |

### License
Before you try to copy any part of the code in this plugin, please read following license before you do.

    Adapted from the Wizardry License

    Copyright (c) 2015-2020 larryTheCoder and contributors

    Permission is hereby granted to any persons and/or organizations
    using this software to copy, modify, merge, publish, and distribute it.
    Said persons and/or organizations are not allowed to use the software or
    any derivatives of the work for commercial use or any other means to generate
    income, nor are they allowed to claim this software as their own.

    The persons and/or organizations are also disallowed from sub-licensing
    and/or trademarking this software without explicit permission from larryTheCoder.

    Any persons and/or organizations using this software must disclose their
    source code and have it publicly available, include this license,
    provide sufficient credit to the original authors of the project (IE: larryTheCoder),
    as well as provide a link to the original project.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
    INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS FOR A PARTICULAR
    PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
    LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
    TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
    USE OR OTHER DEALINGS IN THE SOFTWARE.
