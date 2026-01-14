using System.CodeDom.Compiler;
using System.Collections.Generic;
using System.Collections;
using System.ComponentModel;
using System.Diagnostics.CodeAnalysis;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Reflection;
using System.Runtime.Serialization;
using System.Text.RegularExpressions;
using System.Text;
using System;

class Result
{

    /*
     * Complete the 'getTotalX' function below.
     *
     * The function is expected to return an INTEGER.
     * The function accepts following parameters:
     *  1. INTEGER_ARRAY a
     *  2. INTEGER_ARRAY b
     */

    public static int getTotalX(List<int> a, List<int> b)
{
    static int Gcd(int x, int y) => y == 0 ? x : Gcd(y, x % y);
    
    static int Lcm(int x, int y) => x / Gcd(x, y) * y; 
    
    int lcm_a = a[0];
    for (int i = 1; i < a.Count; i++)
    {
        lcm_a = Lcm(lcm_a, a[i]);
    }
    
    int gcd_b = b[0];
    for (int i = 1; i < b.Count; i++)
    {
        gcd_b = Gcd(gcd_b, b[i]);
    }
    
    if (gcd_b % lcm_a != 0)
        return 0;
    
    int count = 0;
    int multiple = lcm_a;
    while (multiple <= gcd_b)
    {
        if (gcd_b % multiple == 0)
            count++;
        multiple += lcm_a; 
    }
    
    return count;
}

}

class Solution
{
    public static void Main(string[] args)
    {
        TextWriter textWriter = new StreamWriter(@System.Environment.GetEnvironmentVariable("OUTPUT_PATH"), true);

        string[] firstMultipleInput = Console.ReadLine().TrimEnd().Split(' ');

        int n = Convert.ToInt32(firstMultipleInput[0]);

        int m = Convert.ToInt32(firstMultipleInput[1]);

        List<int> arr = Console.ReadLine().TrimEnd().Split(' ').ToList().Select(arrTemp => Convert.ToInt32(arrTemp)).ToList();

        List<int> brr = Console.ReadLine().TrimEnd().Split(' ').ToList().Select(brrTemp => Convert.ToInt32(brrTemp)).ToList();

        int total = Result.getTotalX(arr, brr);

        textWriter.WriteLine(total);

        textWriter.Flush();
        textWriter.Close();
    }
}
