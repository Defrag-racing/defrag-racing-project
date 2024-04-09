const data = `<section class="ruleset">
<h3>1. Intro</h3>
<ul class="ruleset">
    <li>Players have to meet the following criterias in order to participate in the Defrag World Cup 2021.</li>
    <li>In order to participate, players have to <a href="/register">register here</a>.</li>
    <li><em>Note: It is strictly not allowed to share your account with another player.</em></li>
</ul>

<h3>2. Client</h3>
<ul class="ruleset">
    <li>
        If you do not have Defrag (mod) installed, you can <a href="/bundles">download the latest version here</a>.
    </li>
</ul>

<h3>3. Config</h3>
<ul class="ruleset">
    <li>
        Tournament requires some Cvars to be set in order to allow for consistency of each run/demo.<br>
        <a href="/bundles">Download the bundle from the bundles section</a>
    </li>
    <li>
<br>
Additional cvar information:
        <pre>"df_name" must be set to the player name
"df_country" must be set to the country the player is from
"timescale" must be set to "1"
"sv_cheats" must be set to "0"
"handicap" must be set to "100"

"sv_fps" must be set to "125"
"com_maxfps" must be set to "125"
"g_speed" must be set to "320"
"g_gravity" must be set to "800"
"g_knockback" must be set to "1000"

"df_autorecord" must be set to "1"
"df_ar_stopdelay" must be set to a value between "1000" and "10000"

"df_mp_interferenceoff" must be set to "3"
"g_killWallbug" must be set to "1"</pre>
    </li>
</ul>

<h3>4. Demos</h3>

<ul class="ruleset">
    <li class="dig">
        Only demos that are submitted before the round's deadline are accepted into the competition.
    </li>
    <li class="dig">
        The filenames of the submitted demos have to match the following pattern:
        <br><br><strong class="info-text">map[df.physics]time(nick.nationality).dm_68</strong>
    </li>
    <li class="dig">
        Demos have to start with player's (re)spawn and end after defrag reports map completion in the console
        (see demo recording cvars below). Demos without start or end are not accepted.
    </li>
    <li class="dig">
        It is a good practice to send more than one successful demo.
        This ensures that you are not eliminated if your fastest demo is rejected for some reason
        (e.g. corrupt demo, recorded a broken demo, etc.).
    </li>
    <li class="dig">
        Only offline demos on localhost servers are allowed. The players net ping has to be 0. Do not play on a
        dedicated server (no multiplayer demos).
    </li>

    <li class="dig">It is not allowed to use external means to time player actions (jumping, shooting, view
        direction change).<br>
        The player has to time actions himself. (no auto jumping, no replaying)
    </li>

    <li class="dig">
        Note: Demo validators have the right to reject demos with engine/physics exploits
        not listed in these rules, if they violate the common sense of fair play.
        However, this is only a precautional measure and very unlikely to happen.
        In any case, the player as well as the community would be allowed to appeal
        to this before the final decision is made.
        If you are in doubt whether your route will be allowed or not,
        please reach out to one of the Tournament staff.
    </li>
</ul>


<h3 id="cheating">5. Cheating</h3>
<ul class="ruleset">
    <li class="dig">
        It is not allowed to modify the memory used by defrag or its client with external programs during
        runtime of the game.
    </li>
    <li class="dig">
        It is not allowed to use external means to time player actions (jumping, shooting, view direction
        change). The player has to time actions himself or with help of the allowed internal quake3/defrag
        features e.g. cg_drawTimer 1.
    </li>
    <li class="dig">
        <strong>Mousewheel:</strong>
        It is not allowed to bind any actions that alter the movement of a player to your mouse wheel,
        such as; +forward, +back, +moveleft, +moveright, +movedown, +moveup.
        Stick to conventional keybinds such as; +button commands, weapon #, chat binds...etc.
    </li>
    <li class="dig">
        <strong>Bound Rocket-Jumps:</strong>It is not allowed to bind both +moveup and +attack on a single button and use it. It is not allowed to invoke by any means any sequence of commands including both +moveup and +attack in it. For example, if you have +moveup bound to one key, and +attack bound to another key, you can press them down at the same game frame, and release them at the same other frame - it IS allowed. If you use a multi-command bind, or a vstr sequence that contains both of these commands (perhaps, along with “wait”-s or other commands) - it is not allowed to use it.
    </li>
    <li class="dig">
        It is not allowed to modify the pk3 files of any of the rounds - neither replace bsp files, nor
        textures, sounds or any other files. The demo must not contain bsp checksum mismatch warning ("Bsp crc:
        checksum failed").
    </li>
    <li class="dig">
        It is not allowed to modify the demo by external software at any time.
    </li>
    <li class="dig">
        <strong>Yawspeeding:</strong> It is not allowed to change your view angles (direction the player is
        facing) with other means than moving the mouse. This means the usage of commands +left, +right, +lookup,
        +lookdown, centerview is not allowed.
    </li>
    <li class="dig">
        <strong>Botting / Replaying / Scripting:</strong> It is not allowed to record and reproduce player's
        input data using special software or hardware to record a valid demo. It is not allowed
        to execute sequence of commands (triggered by software or hardware) that affect physical player behavior
        using predefined timing and order. Basically any kind of automation is forbidden, all times have to
        be set by hand.
    </li>
    <li class="dig">
        <strong>Timescaling:</strong> It is not allowed to manipulate the rate of time defrag works or
        perceives, using special software or hardware tweaks.
    </li>
</ul>

<h3>6. Map exploits</h3>
<ul class="ruleset">
<li class="dig"><strong>Map decompilation.</strong> <br>
If you need to know where some triggers or player clips are located in a map, you can do it without decompilation this time.<br>
Usage: Download the <a href="/bundles/2/useful-pk3s">Clean trigger, clip and slick brush</a> from the bundles page and put it in your baseq3 folder.
</li>
    <li class="dig">
        <strong>Walllingering / Wallbugs:</strong> It is not allowed to build up large amounts of speed without
        moving.
    </li>
    <li class="dig">
        <strong>Killbugs:</strong> Players have to make sure they don't retain any game states of prior attempts
        to set a time into the current attempt. It is not allowed to exploit behaviour that only occurs when a
        player restarts the map or kills himself.
    </li>
</ul>

<h3>7. Streaming</h3>
<ul class="ruleset">
    <li>
In past streaming during rounds was not allowed due to sharing routes and such.<br>
Cracking down and banning people from competing for doing so is nearly impossible and seems rather unconventional.<br>
At the end of the day its no different than teams or clans that gather together to share routes among each other.<br>
Today streaming is a major part of any video game or competition, it plays a huge role in attracting new players,
allowing them to learn maps and game mechanics collectively.<br>
Therefore, if a streamer decides to share routes with their viewers, by all means do so at your own risk.
    </li>
</ul>
</section>`;

export default data;