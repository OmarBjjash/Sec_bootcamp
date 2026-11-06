#include <stdio.h>
#include <string.h>
#include <ctype.h>

       

void checkPassword(const char *password)
{
    // int max_length = 100;
    int min_length = 8;

    int len = (int)strlen(password);
    int has_lower = 0;
    int has_upper = 0;
    int has_digit = 0;
    int has_symbol = 0;
    int score = 0;

    for (int i = 0; i < len; i++)
    {
        unsigned char c = (unsigned char)password[i];

        if (islower(c))
            has_lower = 1;
        else if (isupper(c))
            has_upper = 1;
        else if (isdigit(c))
            has_digit = 1;
        else if (!isdigit(c) && !isalpha(c)) 
            has_symbol = 1;
    }

    if (len >= min_length) score++;
    if (len >= 12) score++;   
    if (has_lower) score++;   
    if (has_upper) score++;   
    if (has_digit) score++;   
    if (has_symbol) score++;   

    printf("\n========= Password Strength Report =========\n");
    printf("Password length             : %d characters\n", len);
    printf("Contains lowercase letters  : %s\n", has_lower  ? "Yes" : "No");
    printf("Contains uppercase letters  : %s\n", has_upper  ? "Yes" : "No");
    printf("Contains digits             : %s\n", has_digit  ? "Yes" : "No");
    printf("Contains special symbols    : %s\n", has_symbol ? "Yes" : "No");
    printf("Total score                 : %d / 6\n", score);
    printf("--------------------------------------------\n");

    if (len < min_length)
    {
        printf("Result: WEAK (length is less than %d characters)\n", min_length);
    }
    else if (score <= 2)
    {
        printf("Result: WEAK - increase character variety\n");
    }
    else if (score <= 4)
    {
        printf("Result: ACCEPTABLE - can be improved\n");
    }
    else
    {
        printf("Result: STRONG - excellent password\n");
    }
    printf("============================================\n");
}

int main(void)
{
    int max_length = 100;
    char buffer[max_length];

    printf("Enter the password to check: ");

    if (scanf("%99s", buffer) != 1)
    {
        printf("Error reading input.\n");
        return 1;
    }

    checkPassword(buffer);
    return 0;
}