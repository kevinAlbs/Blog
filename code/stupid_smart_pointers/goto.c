#include <stdlib.h>

// SNIPPET_BEGIN:goto
void f() {
    char* resource_1 = NULL, *resource_2 = NULL, *resource_3 = NULL;
    resource_1 = get_resource();
    if (resource_1 == NULL) return;

    resource_2 = get_resource();
    if (resource_2 == NULL) goto free_resource_1;

    resource_3 = get_resource();
    if (resource_3 == NULL) goto free_resource_2;

    /* ... */

free_resource_2:
    free(resource_2); // fall through
free_resource_1:
    free(resource_1);
    return;
}
// SNIPPET_END:goto

int main() {
    f();
}