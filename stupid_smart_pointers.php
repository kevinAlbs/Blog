<?php
require_once("gen.php");
$meta = [
    "title" => "Stupid Smart Pointers in C",
    "description" => "Stupid Smart Pointers in C",
    "subtitle" => "",
    "date" => "March 17, 2018"
];
require_once("inc/header.php");
?>

<p>Managing memory in C is difficult and error prone. C++ solves this with smart pointers like <code>std::unique_ptr</code> and <code>std::shared_ptr</code>. This article demonstrates a proof-of-concept (aka stupid) smart pointer in C with very little code. Along the way we'll look at the layout of the 32-bit x86 call stack and write assembly in a C program.</p>

<!-- TODO: make php functions for section headings. -->
<?= heading("Managing Memory in C") ?>

<p>In C, heap memory is allocated with a call to <code>malloc</code> and deallocated with a call to <code>free</code>. It is the programmer's responsibility to free allocated memory when no longer in use. Otherwise, memory leaks grow the program's memory usage, exhausting valuable system resources.</p>

<p>Sometimes knowing where to call <code>free</code> is clear.</p>

<?= code('c', 'code/stupid_smart_pointers/snippets.c', 'malloc_free') ?>

<p>But even simple cases may be difficult to properly free. For example, suppose a function <code>f</code> allocates resources in order and frees them before returning.</p>

<?= code('c', 'code/stupid_smart_pointers/awkward.c', 'awkward') ?>

<p>Each return must free everything previously allocated. The list of calls to <code>free</code> grows for every additional resource allocated. There are ways to organize this to reduce some redundancy. But the root of the problem remains: the lifetime of the allocated resource is bound to where <code>f</code> returns. Whenever <code>f</code> returns, we need to guarantee all of these resources are freed.</p>

<p>A nice solution in C is described in Eli Bendersky's article: <a href='https://eli.thegreenplace.net/2009/04/27/using-goto-for-error-handling-in-c'>Using goto for error handling in C</a>. This uses the goto statement and places all free calls at the end of the function.</p>

<?= code('c', 'code/stupid_smart_pointers/goto.c', 'goto') ?>

<p>But C++ has an even better solution. Since objects have destructors, we can explicitly bind the lifetime of a pointer to the lifetime of an object.</p>

<?= code('cpp', 'code/stupid_smart_pointers/unique_ptr.cpp', 'unique') ?>

<p>The <code>unique_ptr</code> object wraps around the allocated pointer, and frees it when the <code>unique_ptr</code> goes out of scope.</p>

<p>Unfortunately, C has no destructors we can hook onto, so there are no native smart pointers. But we can create a surprisingly simple approximation.</p>

<?= heading("Implementation") ?>

<p>The smart pointer will only consist of one function, <code>free_on_exit</code>, to free the passed pointer when the current function returns. This will allow us to rewrite our above example without any calls to <code>free</code>.</p>

<?= code('c', 'code/stupid_smart_pointers/free_on_exit_example.c', 'free_on_exit_example') ?>

<p>Wherever <code>f</code> returns, it frees everything allocated before. But how can we possibly implement <code>free_on_exit</code>? How can we know when <code>f</code> returns and free all previous allocations? The trick is to manipulate the call stack. Instead of <code>f</code> returning to its original caller, we can manipulate the stack to have it return to our own custom function.</p>

<?= heading("The Call Stack", 1) ?>

<p>Let's refresh on what the call stack looks like. The layout of the call stack depends on the architecture. We'll use 32 bit x86 as our target architecture (which has a simpler layout and calling conventions than 64 bit). Eli Bendersky has another great article, <a href="https://eli.thegreenplace.net/2011/02/04/where-the-top-of-the-stack-is-on-x86/">Where the top of the stack is on x86</a>, with more depth, but the following is a brief overview.</p>

<p>Here's an example of what the stack looks like when function <code>main</code> calls function <code>sum</code> in 32 bit x86 architecture.</p>

<?= code('c', 'code/stupid_smart_pointers/function_call.c') ?>

<figure>
<img width='100%' src='img/stupid_smart_pointers/function_call.svg' />
<figcaption>The call stack during a function call.</figcaption>
</figure>

<p>During a function call, the caller and callee split the responsibilities of what data to push onto the stack. The caller <code>main</code> is responsible for saving the current <code>eip</code>, but the callee <code>f</code> is responsible for saving the current <code>ebp</code>.</p>

<?= heading("Hijacking a Return Address") ?>

<p>But how can the stack be modified in a C program? One way is to use assembly to obtain stack addresses, and then change the values they point to. The following uses inline assembly to change a function's return address.</p>

<figure>
<?= code('c', 'code/stupid_smart_pointers/hijack.c') ?>
<figcaption>hijack.c</figcaption>
</figure>

<p>To run this program:</p>
<pre><code class='bash'>$ gcc -O0 hijack.c -m32 -o hijack
$ ./hijack
main starts
f starts
f ends
hijacked
Bus error: 10</code></pre>

<p>We can see that <code>f</code> never returns to <code>main</code>, but instead to the hijacked function! How does this work?</p>

<p>In <code>f</code>, we get the current value of the <code>ebp</code> register in <code>base</code> using the inline assembly function <code>__asm__</code>.</p>

<pre><code class='c'>__asm__ (
"movl %%ebp, %0 \n" 
: "=r" (base) // output
);</code></pre>

<p><code>"movl %%ebp, %0 \n"</code> is the assembly instruction we run. Registers are denoted with <code>%%</code>. The <code>%0</code> is a placeholder for the first output variable.</p>

<p><code>: "=r" (base)</code> says use the C variable <code>base</code> as the first output variable. <code>"=r"</code> means store the operand in a register before copying to the output variable.</p>

<p>For more information about <code>__asm__</code>, see the article <a href="http://ericw.ca/notes/a-tiny-guide-to-gcc-inline-assembly.html">A Tiny Guide to GCC Inline Assembly</a> by Eric Woroshow.</p>

<p>Once we have the value of <code>ebp</code> in <code>base</code>, we can use it just like any pointer.</p>

<pre><code class='c'>*(base + 1) = (int) hijacked;</code></pre>

<p>Since <code>base</code> is of type <code>int*</code> adding one increments the address by the size of an int (4 bytes in this case). Therefore, this line changes the saved <code>eip</code> on the stack from <code>main</code> to the address of the function <code>hijacked</code>.</p>

<p>Note, after we return from <code>hijacked</code> there's an error (yours may be a segmentation fault). Next we'll see how to fix that error.</p>

<?= heading("Restoring the Return Address") ?>

<p>The example before ended with an error. When <code>hijacked</code> returns, there isn't an address to pop off of the stack, so it jumps to an invalid address.</p>

<figure><img width='100%' src="img/stupid_smart_pointers/hijack.svg"></figure>

<p>The caller is responsible for pushing the return address. When we jump directly to <code>hijacked</code> we bypass the usual call convention.</p>

<p>Instead we want <code>hijacked</code> to return back to the original return address in <code>main</code>. To do so we can use a pure assembly function to avoid the typical function call and return sequence of a compiled C function.</p>

<figure>
<?= code('x86', 'code/stupid_smart_pointers/trampoline.S', 'trampoline') ?>
<figcaption>trampoline.S</figcaption>
</figure>

<p>This assembly function named <code>trampoline</code> bypasses the usual call sequence generated by compiling a C function. Instead of popping a return address to return to, we <code>jmp</code> directly to the value stored in <code>eax</code>. The value returned by <code>hijacked</code> is stored in <code>eax</code>. We modify <code>hijacked</code> and <code>f</code> as follows:</p>

<?= code('c', 'code/stupid_smart_pointers/hijack_2.c', 'hijack') ?>

<p>Compile and run with:</p>
<pre><code class='bash'>$ gcc -o hijack -O0 -m32 hijack.c trampoline.S
$ ./hijack
main starts
f starts
f ends
hijacked 
main ends</code></pre>

<p>Now our hijacked function restores the original return address after executing. We'll use this same technique to implement our smart pointer.</p>

<?= heading("One Smart Pointer") ?>
<p>We're one small step away from creating a smart pointer. Let's rename <code>hijacked</code> to <code>do_free</code>, and add the function <code>free_on_exit</code>, which now hijacks the <em>caller's</em> return address.</p>

<?= code('c', 'code/stupid_smart_pointers/one_smart_pointer.c') ?>

<p>Calling <code>free_on_exit</code> stores the passed pointer and sets the caller's return address to <code>trampoline</code>. After the caller <code>f</code> returns, it automatically frees its <code>malloc</code>'ed byte. We now have a smart pointer!</p>

<?= heading("Many Smart Pointers") ?>

<p>The <code>free_on_exit</code> above is only a single-use function. If called multiple times, it only frees the pointer passed in the most recent call. Fortunately, it’s only another small step to make <code>free_on_exit</code> work with any number of repeated calls.</p>

<p>To do so we can store a list of tracked pointers for each function call. Stack these lists, and each time a new function calls free_on_exit, add a new stack entry. When do_free is called, it frees the list of pointers on the top most entry of the stack.</p>

<p>At the risk of including too much code in this article, here is the full implementation in under one hundred lines of code:</p>

<figure>
    <?= code('c', 'code/stupid_smart_pointers/smart/smart.h') ?>
    <figcaption>smart.h</figcaption>
</figure>
<figure>
    <?= code('c', 'code/stupid_smart_pointers/smart/smart.c') ?>
    <figcaption>smart.c</figcaption>
</figure>
<figure>
    <?= code('c', 'code/stupid_smart_pointers/smart/trampoline.S') ?>
    <figcaption>trampoline.S</figcaption>
</figure>

<?= heading("Conclusion", 0, 1) ?>

<p>In this article we’ve shown how to build a simple smart pointer on an 32 bit x86 architecture. We’ve looked at the call stack, hijacked return addresses, and written some assembly in the process.</p>

<p>For more reading, check out the following articles:</p>
<ul>
<li><a href="https://github.com/Snaipe/libcsptr">libcsptr</a> a full-fledged smart pointer library written in C</li>
<li><a href="http://ericw.ca/notes/a-tiny-guide-to-gcc-inline-assembly.html">A Guide to inline Assembly</a> by Eric Woroshow</li>
<li><a href="https://eli.thegreenplace.net/2011/02/04/where-the-top-of-the-stack-is-on-x86/">Where the top of the stack is on x86</a> by Eli Bendersky</li>
<li><a href="https://eli.thegreenplace.net/2011/09/06/stack-frame-layout-on-x86-64">Stack frame layout on x86-64</a> by Eli Bendersky</li>
<li><a href="https://eli.thegreenplace.net/2009/04/27/using-goto-for-error-handling-in-c">Using goto for error handling in C</a> by Eli Bendersky</li>
</ul>


<?php
require_once("inc/footer.php");
?>