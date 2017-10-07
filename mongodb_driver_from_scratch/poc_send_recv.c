#include <stdio.h>
#include <ctype.h>
#include <unistd.h> /* close */
#include <sys/types.h>
#include <sys/socket.h> /* socket */
#include <errno.h> /* errno */
#include <netdb.h> /* necessary? */


#include <arpa/inet.h>
#include <string.h>

int main () {   
    /* Create a socket to connect to a mongod instance bound to localhost on port 27017 */
    struct sockaddr_in addr = {0};
    addr.sin_family = AF_INET;
    addr.sin_addr.s_addr = inet_addr("127.0.0.1"); // Converts to network byte ordering. careful, returns -1 on failure.
    addr.sin_port = htons(27017);

    int socket_fd = -1;
    if ((socket_fd = socket(AF_INET, SOCK_STREAM, 0)) == -1) {
        printf("Could not create socket\n");
    }
    
    // Connect to the remote address, associating this socket.
    int ret = connect (socket_fd, (struct sockaddr*)&addr, sizeof (struct sockaddr));
    if (ret != 0) {
        printf("Err: %d\n", errno);
        return 1;
    }

    printf ("Connected\n");

    unsigned char op_msg[] = {
        0x5D, 0x00, 0x00, 0x00, // total message size, including this
        0x02, 0x00, 0x00, 0x00, // identifier for this message
        0x00, 0x00, 0x00, 0x00, // requestID from the original request (used in responses from db)
        0xDD, 0x07, 0x00, 0x00, // opCode = 2013 (0x07DD) for OP_MSG (little-endian)
        0x00, 0x00, 0x00, 0x00, // message flags
        0x00,                   // first and only data section of type 0
        // begin bson command document {insert: "test_coll", documents: [{x:1}]}
        0x48, 0x00, 0x00, 0x00, // total BSON obj length (little-endian) 

        // "insert": "test_coll" key/value
        0x02, 'i','n','s','e','r','t','\0',
        0x0A, 0x00, 0x00, 0x00, // "test_coll" length (little-endian)
        't','e','s','t','_','c','o','l','l','\0',

        // "$db": "db"
        0x02, '$', 'd', 'b', '\0',
        0x03, 0x00, 0x00, 0x00,
        'd', 'b', '\0',

        // "documents": [{"x":1}]
        0x04, 'd','o','c','u','m','e','n','t','s','\0',
        0x16, 0x00, 0x00, 0x00, // start of {"0": {"_id": 1}} 
        0x03, '0', '\0', // key "0"
        0x0E, 0x00, 0x00, 0x00, // start of {"_id": 1}
        0x10, '_', 'i', 'd', '\0', 0x02, 0x00, 0x00, 0x00, // "_id": 1 (little endian)
        0x00,                    // end of {"_id": 1}
        0x00,                    // end of {"0": {"_id": 1}}
        0x00                     // end of command document
    };

    for (int i = 0; i < sizeof(op_msg); i++) {
        printf("%02x ", op_msg[i]);
    }
    printf("\n");
    send (socket_fd, op_msg, sizeof(op_msg), 0);
    printf("message sent\n");
    unsigned char buf[1024] = {0};
    recv (socket_fd, buf, 1024, 0);
    printf("message received\n");
    for (int i = 0; i < 1024; i++) {
        if (isascii(buf[i])) printf("%c", buf[i]);
        else printf(".");
    }
    printf("\n");
    close (socket_fd); 
	// the socketaddr type is in res->ai_addr
	// see man getaddrinfo

	//std::cout << res->ai_canonname << std::endl;
	// freeaddrinfo (remote_addr);
}
