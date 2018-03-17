__Note__ The content here is out of date. See the google doc.

# Proposal
Managing memory in C is hard. We'll walk through how to implement a simple (or stupid) smart pointer implementation in C in less than 80 lines of code! In the process we'll learn how to hack the function stack with some x86 assembly.

Here's an outline for the talk:
1. What is a smart pointer?
2. How to implement a smart pointer in C
  a. The layout of a stack frame in 32 bit x86.
  b. Hijacking a function's return address to point to a custom free function.
  c. Using an assembly trampoline to restore the stack.
3. Conclusion: A cute smart pointer library in very little code.

# Stupid Smart Pointers in C
## Overview
Managing memory in C is difficult and error-prone. C++ solves this with smart pointers like std::unique_ptr and std::shared_ptr. This article demonstrates a proof-of-concept (aka stupid) smart pointer in C with very little code. Along the way we'll cover the layout of the 32-bit x86 call stack and embed x86 assembly in a C program.

## Managing Memory in C
In C, heap memory is allocated with a call to `malloc` and deallocated with a call to `free`. It is the programmer's responsibility to free allocated memory when no longer in use. Otherwise memory leaks grow the program's memory usage, potentially until all available system memory is exhausted.

Sometimes knowing where to call `free` is clear.
```
char* data = (char*)malloc(100);
/* do something with data, don't need it anymore */
free(data);
```

But even in simple cases it may be awkward to properly free. For example, suppose a function `f` allocates resources in order and frees them before returning:

```
void f() {
    char* resource_1 = get_resource();
    if (resource_1 == NULL) return;

    char* resource_2 = get_resource();
    if (resource_2 == NULL) {
        free(resource_1);
        return;
    }

    char* resource_3 = get_resource();
    if (resource_3 == NULL) {
        free(resource_2);
        free(resource_1);
        return;
    }

    /* ... */
}
```

The awkwardness is that each return must free everything that was previously allocated. The list of calls to `free` grows for every additional resource allocated. There are ways to organize this to reduce some redundancy. But the root of the problem remains: the lifetime of the allocated resource is bound to where `f` returns. Whenever `f` returns, we need to guarantee all of these resources are freed.

A nice solution in C is described in [Eli Bendersky's article|https://eli.thegreenplace.net/2009/04/27/using-goto-for-error-handling-in-c]. This uses the `goto` statement and places all free calls at the end of the function.

```
void f() {
    char* resource_1 = get_resource();
    if (resource_1 == NULL) return;

    char* resource_2 = get_resource();
    if (resource_2 == NULL) goto free_resource_1;

    char* resource_3 = get_resource();
    if (resource_3 == NULL) goto free_resource_2;

    /* ... */

free_resource_2:
    free(resource_2); // fall through
free_resource_1:
    free(resource_1);
    return;
}
```


But C++ has an even better solution. Since objects have destructors, we can explicitly bind the lifetime of a pointer to the lifetime of an object.

```
void f() {
    std::unique_ptr<char> resource_1 = std::make_unique<char>(get_resource());
    if (resource_1.get() == nullptr) return;
    std::unique_ptr<char> resource_2 = std::make_unique<char>(get_resource());
    if (resource_2.get() == nullptr) return;
    std::unique_ptr<char> resource_3 = std::make_unique<char>(get_resource());
    if (resource_3.get() == nullptr) return;
    /* ... */
}
```

The `unique_ptr` object wraps around the allocated pointer, and frees it when the unique_ptr goes out of scope.

Unfortunately C has no destructors we can hook onto, so there are not native smart pointers. But we can create a surprisingly simple approximation with very little code.

## The Implementation

To keep things simple, our implementation is going to define one function, `free_on_exit` to free a given pointer when the function returns. This will allow us to rewrite our above example without any calls to `free`.

```
void f() {
    char* resource_1 = free_on_exit(get_resource());
    if (resource_1 == NULL) return;

    char* resource_2 = free_on_exit(get_resource());
    if (resource_2 == NULL) return;

    char* resource_3 = free_on_exit(get_resource());
    if (resource_3 == NULL) return;
}
```
Wherever `f` returns, it frees everything allocated before. But how can we implement `free_on_exit`? How can we know when `f` returns and free all previous allocations? The trick is to manipulate the call stack. When `f` returns, it won't return to the original caller, but rather our own function.

### Hijacking a Return Address
To manipulate the call stack, the exact layout must be known beforehand. The stack layout depends on the architecture. Going forward, we'll use 32 bit x86 as our target architecture (which has a simpler layout than 64 bit). Eli Bendersky has a [great article](https://eli.thegreenplace.net/2011/02/04/where-the-top-of-the-stack-is-on-x86/) with more depth, but the following is a brief overview.

Each function call keeps track of its state by pushing data onto the stack frame. The caller and callee are responsible for different parts of setting up and cleaning up the stack frames. The exact location of the data depends on the architecture. Here's an example of what the stack looks like after a function `main` calls function `f` in 32 bit x86 architecture.

TODO: draw this.


(inside main)                               (main calls f)                (f returns)
...
|  local in main      |  \ stack frame for main
|  arg for f          |  /
|  saved eip for main | -
|  saved ebp for main |
|  local for f2       | <- esp
   top of stack


But how can we modify the stack? One way is to use assembly to read registers directly. The following uses inline assembly to change a function's return address.

```
#include <stdio.h>
void hijacked() {
    printf("hijacked\n");
}
void f() {
    printf("f starts\n");

    int* ebp;
    // get the value of the ebp.
    __asm__ (
    "movl %%ebp, %0 \n" 
    : "=r" (ebp) // output
    );

    // change the return address.
    *(ebp + 1) = (int) hijacked;

    printf("f ends\n");
}
int main() {
    printf("main starts\n");
    f();
    printf("main ends\n");
}
```

To run this program:
```
$ gcc -O0 example-stack.c -m32 -o example-stack
$ ./example-stack
start function main
start function f
end function f
hijacked
Bus error: 10
```

We can see that f never returns to main, but instead to the hijacked function! How does this work?

We store the current value of the ebp register in `f`. The syntax for inline assembly __asm__ has peculiarities. Let's break down the one above.
```
__asm__ (
"movl %%ebp, %0 \n" 
: "=r" (base) // output
);
```
`"movl %%ebp, %0 \n"` is the assembly instruction we want to run. Registers are denoted with "%%" sign. The %0 is a placeholder for the first output variable.
`: "=r" (base)` says use my C variable `base` as the first output variable. "=r" means store the operand in a register before setting the output variable.

There is a great article by [http://ericw.ca/notes/a-tiny-guide-to-gcc-inline-assembly.html] explaining the __asm__ function. It goes into much more detail.

Note, after we return from hijacked there's an error (yours may be a segmentation fault). Let's fix that.

### Restoring the Return Address
The example before ended with an error when `hijacked` returns. By the time `hijacked` returns, there isn't an address to pop off of the stack.

<pictures>

The caller is responsible for pushing the return address. When we jump directly to `hijacked` we <go around> the usual call sequence.

Instead we want to have `hijacked` return back to the original return address in `main`. To do so we can't use the default caller/callee seqence of instructions of a compiled C function. Instead, we'll use use a pure assembly function which doesn't follow the usual callee return sequence. This pure assembly function will `call` our hijacked function as a normal call sequence. When `hijacked` returns, it will return the original return address. The assembly will then `jump` directly to that address instead of following the normal `return` sequence.

<code>

### Freeing One Pointer
We're one step away from creating a smart pointer. Let's start by defining `free_on_exit` to work with one pointer. Here's the definition of `free_on_exit`.

<code>

`free_on_exit` does the following:
- it takes the passed pointer and tracks it by storing in a global
- it saves the callee's return address and changes it to the `do_free` function
- `do_free` frees the pointer and restores the original return address

### Freeing Many Pointers
If `free_on_exit` is called multiple times, it only frees the pointer passed in the most recent call. Fortunately it is a small step to make `free_on_exit` work with any number of pointers. Store a list of "tracked" pointers for each function call. And make a stack of these lists, corresponding to the call stack. Then `do_free` function frees all of the pointers on the top of the stack.

At the risk of filling this article with code, here is the complete implementation:

## Note
- who calls main?


## Conclusion
We've shown a proof-of-concept of building a smart pointer in C. It has huge limitations. It only works in 32 bit x86. And it only tracks a finite number of pointers. But along the way we've learned that with a little fore-knowledge of how the function stack is laid out you can do some neat things.

