#include <stdlib.h>

// SNIPPET_BEGIN:awkward
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
// SNIPPET_END:awkward

int main () {
    f();
}