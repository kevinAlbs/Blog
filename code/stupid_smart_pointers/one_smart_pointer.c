#include <stdio.h>
int return_address;
void* tracked_pointer;
int do_free() {
    free (tracked_pointer);
    return return_address;
}
void* free_on_exit(void* ptr) {
    // hijack the caller’s return address.
    int* base;
    // get the value of the caller’s ebp by dereferencing ebp.
    __asm__ (
    "movl (%%ebp), %0 \n"
    : "=r" (base) // output
    );

    // save the caller’s return address.
    return_address = *(base + 1);
    // change the return address.
    *(base + 1) = (int) hijacked;

}
void f() {
    char* resource = free_on_exit(malloc(1));
}
int main() {
    f();
}
