#include <stdio.h>
#include <stdlib.h>
/* forward declare the assembly trampoline. */
void trampoline ();
int return_address;
void *tracked_pointer;

int do_free () {
   free (tracked_pointer);
   return return_address;
}

void *free_on_exit (void *ptr) {
   int *base;
   // get the value of the caller's ebp by dereferencing ebp.
   __asm__("movl (%%ebp), %0 \n"
           : "=r"(base) // output
           );

   // save and change the caller's return address.
   return_address = *(base + 1);
   *(base + 1) = (int) trampoline;
   return ptr;
}

void f () {
   char *resource = free_on_exit (malloc (1));
}

int main () {
   f ();
}
