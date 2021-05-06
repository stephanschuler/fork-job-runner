Fork Job Runner
===============

Creating child processes is expensive, especially when this
comes with bootstrapping huge parts of a framework.

This package plays with `proc_open()` and `pcntl_fork()` and
aims to provide a mechanism to execute some kind of `Job`
object in a sub process without having to bootstrap framework
stuff over and over.