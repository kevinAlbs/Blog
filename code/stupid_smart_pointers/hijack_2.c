#include <stdio.h>
// SNIPPET_BEGIN:hijack
int return_address;
int hijacked() {
    printf("hijacked\n");
    return return_address;
}
void f() {
    printf("f starts\n");

    int* base;
    // get the value of the ebp.
    __asm__ (
    "movl %%ebp, %0 \n" 
    : "=r" (base) // output
    );

    // save the return address.
    return_address = *(base + 1);
    // change the return address.
    *(base + 1) = (int) trampoline;

    printf("f ends\n");
}
// SNIPPET_END:hijack
int main() {
    printf("main starts\n");
    f();
    printf("main ends\n");
}
