#include <stdio.h>
void hijacked() {
    printf("hijacked\n");
}
void f() {
    printf("f starts\n");

    int* base = NULL;
    // get the value of ebp.
    __asm__ (
    "movl %%ebp, %0 \n" 
    : "=r" (base) // output
    );

    // change the return address.
    *(base + 1) = (int) hijacked;

    printf("f ends\n");
}
int main() {
    printf("main starts\n");
    f();
    printf("main ends\n");
}
