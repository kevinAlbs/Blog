// compile with: gcc -O0 example-stack.c
#include <stdio.h>
void hijacked() {
    printf("hijacked");
}
void f(int arg) {
    int* ptr = &arg;
    ptr -= 1;
    *ptr = (int)hijacked;
}
int main() {
    f(0xDEAD);
    printf("exiting main\n");
}