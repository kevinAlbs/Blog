#include <memory>

// SNIPPET_BEGIN:unique
void f() {
    std::unique_ptr<char> resource_1 = std::make_unique<char>(get_resource());
    if (resource_1.get() == nullptr) return;
    std::unique_ptr<char> resource_2 = std::make_unique<char>(get_resource());
    if (resource_2.get() == nullptr) return;
    std::unique_ptr<char> resource_3 = std::make_unique<char>(get_resource());
    if (resource_3.get() == nullptr) return;
    /* ... */
}
// SNIPPET_END:unique

int main() {
    f();
}